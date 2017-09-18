<?php

namespace Vectoholic\NeosCli\Domain\Service;
/*
 * This file is part of the Vectoholic.NeosCli package.
 */

use Neos\Flow\Composer\ComposerUtility;
use Neos\Flow\Package\Package;
use Neos\Utility\Files;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Xliff\XliffParser;
use Neos\FluidAdaptor\View\StandaloneView;
use Neos\Flow\Package\PackageManagerInterface;
use Symfony\Component\Yaml\Yaml;
use Vectoholic\NeosCli\Exception\GeneratorException;

/**
 * Service for the file and folder generator
 *
 */
class GeneratorService {

    /**
     * @Flow\Inject
     * @var PackageManagerInterface
     */
    protected $packageManager;

    /**
     * @var array
     */
    protected $generatedFiles = array();

    /**
     *
     * @param string $fileType
     * @param array $settings
     * @param bool $commandArgument
     * @return bool
     * @throws GeneratorException
     */
    public function resolveAutoGenerationStatus(string $fileType, array $settings, bool $commandArgument) {
        if ($settings[$fileType]['autoGenerate'] === null || $settings[$fileType]['autoGenerate'] === '') {
            throw new GeneratorException(sprintf('Please specify autoGenerate in your settings.yaml', $fileType), 201709100);
        } else {
            return  ($commandArgument == false) ? $settings[$fileType]['autoGenerate'] : !$commandArgument ;
        }
    }
    
    public function resolvePackageKey(string $packageKeyfromSettings, string $packageKeyFromCommand) {

        $isPackageKeyFromComandValid = $this->packageManager->isPackageKeyValid($packageKeyFromCommand);
        $isPackageFromCommandAvailable = $this->packageManager->isPackageAvailable($packageKeyFromCommand);
        $isPackageFromCommandActive = $this->packageManager->isPackageActive($packageKeyFromCommand);

        $isPackageKeyFromSettingsValid = $this->packageManager->isPackageKeyValid($packageKeyfromSettings);
        $isPackageFromSettingsAvailable = $this->packageManager->isPackageAvailable($packageKeyfromSettings);
        $isPackageFromSettingsActive = $this->packageManager->isPackageActive($packageKeyfromSettings);

        $isDefaultPackageAvailable = $this->packageManager->isPackageAvailable($this->getSitePackageKey());
        $isDefaultPackageActive = $this->packageManager->isPackageActive($this->getSitePackageKey());

        if ($isPackageKeyFromComandValid && $isPackageFromCommandAvailable && $isPackageFromCommandActive) {
            return $packageKeyFromCommand;

        } elseif ($isPackageKeyFromSettingsValid && $isPackageFromSettingsAvailable && $isPackageFromSettingsActive) {
            return $packageKeyfromSettings;

        } elseif ($isDefaultPackageAvailable && $isDefaultPackageActive) {
            return $this->getSitePackageKey();
        } else {
            throw new GeneratorException(
                sprintf(
                    'None of the following Package-Keys are either valid, available nor active: 
                     From commandline => %s
                     From settings.yaml => %s
                     From the system => %s

                    ',$packageKeyFromCommand, $packageKeyfromSettings, $this->getSitePackageKey()), 201709102);
        }
    }

    /**
     * Generate nodeType configuration file
     *
     * @param string $name
     * @param string $icon
     * @param string $packageKey
     * @param array $superTypes
     * @param bool $document
     * @param array $nodeTypePrefixSettings
     * @param bool $force
     */
    public function generateNodeType(string $name, string $icon, string $packageKey, array $superTypes, bool $document, array $nodeTypePrefixSettings, bool $force) {
        $content = array(
            $packageKey . ':' . $name => array(
                'superTypes' => $superTypes,
                'ui'         => array(
                    'label'  => 'i18n',
                    'icon'   => $icon
                )
            )
        );

        if (empty($nodeTypePrefixSettings)) {
            $nodeTypePrefix = '';
        } elseif (isset($nodeTypePrefixSettings['fromCommandLine'])) {
            $nodeTypePrefix = $nodeTypePrefixSettings['fromCommandLine'] . '.';
        } elseif ($document == true) {
            $nodeTypePrefix = $nodeTypePrefixSettings['documentNodeTypes'] . '.';
        } else {
            $nodeTypePrefix = $nodeTypePrefixSettings['contentNodeTypes'] . '.';
        }

        $yamlContent = Yaml::dump($content, 3, 2);

        $packageConfigPath = $this->packageManager->getPackage($packageKey)->getConfigurationPath();
        $fileName = 'NodeTypes.' . $nodeTypePrefix . $name . '.yaml';

        $targetPathAndFilename = $packageConfigPath . $fileName;
        $this->generateFile($targetPathAndFilename, $yamlContent, $force);
    }

    /**
     * Generate fusion file
     *
     * @param string $name
     * @param string $packageKey
     * @param string $targetPath
     * @param bool $subFolder
     * @param string $nameAppendix
     * @param string $suffix
     * @param string $templatePath
     * @param string $extends
     * @param bool $force
     */
    public function generateFusion(string $name, string $packageKey, string $targetPath, bool $subFolder,string $nameAppendix ,string $suffix, string $templatePath, string $extends, bool $force) {
        $fusionPath = 'resource://' . $packageKey . '/Private/Fusion/';

        $subFolder = ($subFolder === false) ? '' : $name;
        
        $targetPathAndFileName = Files::concatenatePaths([$fusionPath, $targetPath, $subFolder, $name . $nameAppendix . $suffix]);
        $contextVariables = [];
        $contextVariables['packageKey'] =  $packageKey;
        $contextVariables['name'] =  $name;
        $contextVariables['extends'] =  $extends;
        $contextVariables['nameAppendix'] =  $nameAppendix;
        $fusionTemplate = 'FusionTemplate.fusion.tmpl';
        $templatePathAndFileName = Files::concatenatePaths([$templatePath, $fusionTemplate]);
        $fileContent = $this->renderTemplate($templatePathAndFileName,$contextVariables);

        $this->generateFile($targetPathAndFileName, $fileContent, $force);
    }

    /**
     * Generate template file for the view
     *
     * @param string $name
     * @param string $packageKey
     * @param string $targetPath
     * @param bool $subFolder
     * @param string $suffix
     * @param string $templatePath
     * @param bool $force
     */
    public function generateTemplate(string $name, string $packageKey, string $targetPath, bool $subFolder, string $suffix, string $templatePath, bool $force) {
        $viewPath = 'resource://' . $packageKey . '/Private/';
        $subFolder = ($subFolder === false) ? '' : $name;

        $targetPathAndFileName = Files::concatenatePaths([$viewPath, $targetPath, $subFolder, $name . $suffix]);
        $fusionTemplate = 'ViewTemplate.html.tmpl';
        $templatePathAndFileName = Files::concatenatePaths([$templatePath, $fusionTemplate]);
        $fileContent = $this->renderTemplate($templatePathAndFileName,['name' => $name]);

        $this->generateFile($targetPathAndFileName, $fileContent, $force);
    }

    /**
     * Generate javascript file
     *
     * @param string $name
     * @param string $packageKey
     * @param string $targetPath
     * @param bool $subFolder
     * @param string $suffix
     * @param string $templatePath
     * @param bool $force
     */
    public function generateJavascript(string $name, string $packageKey, string $targetPath, bool $subFolder, string $suffix, string $templatePath, bool $force) {
        $viewPath = 'resource://' . $packageKey . '/';
        $subFolder = ($subFolder === false) ? '' : $name;

        $targetPathAndFileName = Files::concatenatePaths([$viewPath, $targetPath, $subFolder, $name . $suffix]);
        $fusionTemplate = 'JavascriptTemplate.js.tmpl';
        $templatePathAndFileName = Files::concatenatePaths([$templatePath, $fusionTemplate]);
        $fileContent = $this->renderTemplate($templatePathAndFileName,['name' => $name]);

        $this->generateFile($targetPathAndFileName, $fileContent, $force);
    }

    /**
     * Generate stylesheet file
     *
     * @param string $name
     * @param string $packageKey
     * @param string $targetPath
     * @param bool $subFolder
     * @param string $suffix
     * @param string $templatePath
     * @param bool $force
     */
    public function generateStylesheet(string $name, string $packageKey, string $targetPath, bool $subFolder, string $suffix, string $templatePath, bool $force) {
        $viewPath = 'resource://' . $packageKey . '/';
        $subFolder = ($subFolder === false) ? '' : $name;
        $targetPathAndFileName = Files::concatenatePaths([$viewPath, $targetPath, $subFolder, $name . $suffix]);
        $fusionTemplate = 'stylesheetTemplate.css.tmpl';

        // convert Name (CamelCase) to snake-case
        $name[0] = strtolower($name[0]);
        $func = create_function('$c', 'return "-" . strtolower($c[1]);');
        $class =  preg_replace_callback('/([A-Z])/', $func, $name);

        $templatePathAndFileName = Files::concatenatePaths([$templatePath, $fusionTemplate]);
        $fileContent = $this->renderTemplate($templatePathAndFileName,['class' => $class]);

        $this->generateFile($targetPathAndFileName, $fileContent, $force);
    }

    /**
     * Generate translation for the package key
     *
     * @param string $name
     * @param string $packageKey
     * @param string $sourceLanguageKey
     * @param array $targetLanguageKeys
     * @param string $templatePath
     * @param string $targetPath
     * @param string $suffix
     * @return array An array of generated filenames
     */
    public function generateTranslation(string $name, string $packageKey, string $sourceLanguageKey, array $targetLanguageKeys, string $templatePath, string $targetPath, string $suffix) {
        $translationPath = 'resource://' . $packageKey . '/Private/Translations';
        $contextVariables = [];
        $contextVariables['packageKey'] = $packageKey;
        $contextVariables['sourceLanguageKey'] = $sourceLanguageKey;
        $contextVariables['name'] = $name;
        $sourceLanguageTemplate = 'SourceLanguageTemplate.xlf.tmpl';

        $templatePathAndFilename = Files::concatenatePaths([$templatePath, $sourceLanguageTemplate]);
        $fileContent = $this->renderTemplate($templatePathAndFilename, $contextVariables);
        $sourceLanguageFile = Files::concatenatePaths([$translationPath, $sourceLanguageKey,$targetPath, $name . $suffix]);
        $this->generateFile($sourceLanguageFile, $fileContent);

        if ($targetLanguageKeys) {
            $xliffParser = new XliffParser();
            $parsedXliffArray = $xliffParser->getParsedData($sourceLanguageFile);

            foreach ($targetLanguageKeys as $targetLanguageKey) {
                $contextVariables['targetLanguageKey'] = $targetLanguageKey;
                $contextVariables['translationUnits'] = $parsedXliffArray['translationUnits'];
                $targetLanguageTemplate = 'TargetLanguageTemplate.xlf.tmpl';
                $templatePathAndFilename = Files::concatenatePaths([$templatePath, $targetLanguageTemplate]);
                $fileContent = $this->renderTemplate($templatePathAndFilename, $contextVariables);
                $targetPathAndFilename = Files::concatenatePaths([$translationPath, $targetLanguageKey, $targetPath, $name . $suffix]);
                $this->generateFile($targetPathAndFilename, $fileContent);
            }
        }
    }

    /**
     * @return array
     */
    public function getGeneratedFiles() {
        return $this->generatedFiles;
    }

    /**
     * Generate a file with the given content and add it to the
     * generated files
     *
     * @param string $targetPathAndFilename
     * @param string $fileContent
     * @param boolean $force
     * @return void
     */
    protected function generateFile($targetPathAndFilename, $fileContent, $force = false){
        if (!is_dir(dirname($targetPathAndFilename))) {
            \Neos\Utility\Files::createDirectoryRecursively(dirname($targetPathAndFilename));
        }
        if (substr($targetPathAndFilename, 0, 11) === 'resource://') {
            list($packageKey, $resourcePath) = explode('/', substr($targetPathAndFilename, 11), 2);
            $relativeTargetPathAndFilename = $packageKey . '/Resources/' . $resourcePath;
        } elseif (strpos($targetPathAndFilename, 'Tests') !== false) {
            $relativeTargetPathAndFilename = substr($targetPathAndFilename, strrpos(substr($targetPathAndFilename, 0, strpos($targetPathAndFilename, 'Tests/') - 1), '/') + 1);
        } else {
            $relativeTargetPathAndFilename = substr($targetPathAndFilename, strrpos(substr($targetPathAndFilename, 0, strpos($targetPathAndFilename, 'Classes/') - 1), '/') + 1);
        }
        if (!file_exists($targetPathAndFilename) || $force === true) {
            file_put_contents($targetPathAndFilename, $fileContent);
            $this->generatedFiles['Created .../' . $relativeTargetPathAndFilename] = 'success';
        } else {
            $this->generatedFiles['Omitted as file already exists .../' . $relativeTargetPathAndFilename] = 'error';
        }
    }

    /**
     * Render the given template file with the given variables
     *
     * @param string $templatePathAndFilename
     * @param array $contextVariables
     * @return string
     * @throws \Neos\FluidAdaptor\Core\Exception
     */
    protected function renderTemplate($templatePathAndFilename, array $contextVariables) {
        $standaloneView = new StandaloneView();
        $standaloneView->setTemplatePathAndFilename($templatePathAndFilename);
        $standaloneView->assignMultiple($contextVariables);
        return $standaloneView->render();
    }

    protected function getSitePackageKey() {
        $packages = $this->packageManager->getAvailablePackages();

        /** @var  $sitePackage Package */
        $sitePackage = end($packages);
        $composerManifest = ComposerUtility::getComposerManifest($sitePackage->getPackagePath());

        $packageType = (isset($composerManifest['type'])) ? $composerManifest['type'] : null;

        if ($packageType == 'neos-site') {
            return $sitePackage->getPackageKey();
        } else {
            return null;
        }
    }

}