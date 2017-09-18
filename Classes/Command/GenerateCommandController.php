<?php
namespace Vectoholic\NeosCli\Command;

/*
 * This file is part of the Vectoholic.NeosCli package.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Vectoholic\NeosCli\Domain\Service\GeneratorService;

/**
 * @Flow\Scope("singleton")
 */
class GenerateCommandController extends CommandController {

    /**
     * @Flow\InjectConfiguration(path="languages")
     * @var array
     */
    protected $languages;

    /**
     * @Flow\InjectConfiguration(path="nodeType")
     * @var array
     */
    protected $nodeTypeConfig;

    /**
     * @Flow\InjectConfiguration(path="component")
     * @var array
     */
    protected $componentConfig;

    /**
     * @Flow\Inject
     * @var GeneratorService
     */
    protected $generatorService;

    /**
     * @Flow\InjectConfiguration(path="packageKey")
     * @var string
     */
    protected $packageKey;

    /**
     * Creates a set of files needed to create an NodeType Element
     *
     * Description to be created
     *
     * @param string $name The Name of the NodeType
     * @param string $icon
     * @param string $packageKey The Package Key where the files should be generated in
     * @param string $s A comma seperated list of superTypes
     * @param string $nodeTypePrefix
     * @param bool $noFusion A flag to disable fusion file auto generation
     * @param bool $noTemplate A flag to disable template file auto generation
     * @param bool $noJs A flag to disable javascript file auto generation
     * @param bool $noStyles A flag to disable stylesheet auto generation
     * @param bool $noTranslation A flag to disable translation auto generation
     * @param bool $nodeTypeOnly A flag to render only the NodeTypes.yaml file
     * @param bool $document A flag to change the defaultNodeType to what is defined in the settings.yaml
     * @param bool $force If a file already exists set this flag to force overriding
     * @throws GeneratorService
     * @throws \Neos\Flow\Mvc\Exception\StopActionException
     */
    public function nodeTypeCommand(
        string $name,
        string $icon = '',
        string $packageKey = '',
        string $s = '',
        string $nodeTypePrefix = '',
        bool $noFusion = false,
        bool $noTemplate = false,
        bool $noJs = false,
        bool $noStyles = false,
        bool $noTranslation = false,
        bool $nodeTypeOnly = false,
        bool $document = false,
        bool $force = false
    ) {

        $packageKey = $this->resolvePackageKey($packageKey);
        $defaultNodeType = ($document == true) ? $this->nodeTypeConfig['defaultDocumentNodeType'] : $this->nodeTypeConfig['defaultContentNodeType'];

        $s = explode(',', $s . ',' . $defaultNodeType);
        $s = array_flip(array_filter($s));
        $superTypes = array();
        foreach ($s as $superType => $value) {
            $superTypes[$superType] = true;
        }

        $nodeTypePrefixSettings = ($this->nodeTypeConfig['nodeTypeNamePrefix'] == false) ? array() : $this->nodeTypeConfig['nodeTypeNamePrefix'];
        $nodeTypePrefixSettings = ($nodeTypePrefix == '') ? $nodeTypePrefixSettings :  array('fromCommandLine' => $nodeTypePrefix);

        if ($nodeTypeOnly) {
            $this->generatorService->generateNodeType($name,$icon,$packageKey,$superTypes,$document,$nodeTypePrefixSettings,$force);

        } else {

            $subFolderStatus = (isset($this->nodeTypeConfig['subFolder'])) ? $this->nodeTypeConfig['subFolder'] : false;

            $this->generatorService->generateNodeType($name,$icon,$packageKey,$superTypes,$document,$nodeTypePrefixSettings,$force);

            $autoGenerateFusion = $this->generatorService->resolveAutoGenerationStatus('fusion',$this->nodeTypeConfig, $noFusion);

            if ($autoGenerateFusion) {
                $this->fusionCommand('nodeType', $name, $packageKey, $subFolderStatus);
            }

            $autoGenerateTemplate = $this->generatorService->resolveAutoGenerationStatus('template',$this->nodeTypeConfig, $noTemplate);

            if ($autoGenerateTemplate) {
                $this->templateCommand('nodeType', $name, $packageKey, $subFolderStatus);
            }

            $autoGenerateJavascript = $this->generatorService->resolveAutoGenerationStatus('javascript',$this->nodeTypeConfig,$noJs);

            if ($autoGenerateJavascript) {
                $this->javascriptCommand('nodeType', $name, $packageKey, $subFolderStatus);
            }

            $autoGenerateStyles = $this->generatorService->resolveAutoGenerationStatus('styles',$this->nodeTypeConfig, $noStyles);

            if ($autoGenerateStyles) {
                $this->stylesheetCommand('nodeType', $name, $packageKey, $subFolderStatus);
            }

            $autoGenerateTranslation = $this->generatorService->resolveAutoGenerationStatus('translation',$this->nodeTypeConfig, $noTranslation);

            if ($autoGenerateTranslation) {
                $this->translationCommand('nodeType', $name, $packageKey);
            }
        }

        $this->outputStatus();

        $this->quit(0);
    }

    public function componentCommand(
        string $name,
        string $packageKey = '',
        string $extends = '',
        bool $noTemplate = false,
        bool $noJs = false,
        bool $noStyles = false,
        bool $noTranslation = false,
        bool $force = false
    ) {
        $packageKey = $this->resolvePackageKey($packageKey);
        $subFolderStatus = (isset($this->componentConfig['subFolder'])) ? $this->componentConfig['subFolder'] : false;
        $extendsFromConfig = (isset($this->componentConfig['fusion']['defaultPrototype'])) ? $this->componentConfig['fusion']['defaultPrototype'] : '';
        $extends = ($extends === '') ? $extendsFromConfig : $extends;

        $autoGenerateFusion = $this->generatorService->resolveAutoGenerationStatus('fusion',$this->componentConfig, false);

        if ($autoGenerateFusion) {
            $this->fusionCommand('component', $name, $packageKey, $subFolderStatus, $extends);
        }

        $autoGenerateTemplate = $this->generatorService->resolveAutoGenerationStatus('template',$this->componentConfig, $noTemplate);

        if ($autoGenerateTemplate) {
            $this->templateCommand('component', $name, $packageKey, $subFolderStatus);
        }

        $autoGenerateJavascript = $this->generatorService->resolveAutoGenerationStatus('javascript',$this->componentConfig,$noJs);

        if ($autoGenerateJavascript) {
            $this->javascriptCommand('component', $name, $packageKey, $subFolderStatus);
        }

        $autoGenerateStyles = $this->generatorService->resolveAutoGenerationStatus('styles',$this->componentConfig, $noStyles);

        if ($autoGenerateStyles) {
            $this->stylesheetCommand('component', $name, $packageKey, $subFolderStatus);
        }

        $autoGenerateTranslation = $this->generatorService->resolveAutoGenerationStatus('translation',$this->componentConfig, $noTranslation);

        if ($autoGenerateTranslation) {
           $this->translationCommand('component',$name, $packageKey);
        }

        $this->outputStatus();
        
        $this->quit(0);
    }

    /**
     * Create a single fusion file
     *
     * @param string $name The name of the file and the prototype
     * @param string $packageKey (optional) The package key where to create the file. Can be set in Settings.yaml globally
     * @param bool $subFolder (optional) Define a subPath inside the Resources/Private/Fusion
     * @param string $extends (optional) The name of the extended prototype
     * @param string $configType Tell the method which settings to choose (nodeType or component)
     * @param string $nameAppendix (optional)
     * @param string $suffix (optional) The suffix of the fusion file
     * @param bool $force (optional) Force overriding of existing files
     */
    public function fusionCommand(string $configType, string $name, string $packageKey = '' ,bool $subFolder = false ,string $extends = '' ,string $nameAppendix = '', string $suffix = '', bool $force = false) {
        $packageKey = $this->resolvePackageKey($packageKey);
        $dynamicConfig = call_user_func([$this, 'get' . $configType . 'Config']);

        $targetPath = (isset($dynamicConfig['fusion']['targetPath'])) ? $dynamicConfig['fusion']['targetPath'] : '';
        $templatePath = (isset($dynamicConfig['fusion']['templatePath'])) ? $dynamicConfig['fusion']['templatePath'] : 'resource://Vectoholic.NeosCli/Private/Templates/Generator/Fusion';
        $nameAppendixFromConfig = (isset($dynamicConfig['fusion']['nameAppendix'])) ? $dynamicConfig['fusion']['nameAppendix'] : '';
        $nameAppendix = ($nameAppendix === '') ? $nameAppendixFromConfig : $nameAppendix;
        
        $suffixFromConfig = (isset($dynamicConfig['fusion']['suffix'])) ? $dynamicConfig['fusion']['suffix'] : '';
        $suffix = ($suffix === '') ? $suffixFromConfig : $suffix;

        $this->generatorService->generateFusion($name, $packageKey, $targetPath, $subFolder, $nameAppendix, $suffix, $templatePath, $extends, $force);

        $calledFrom = debug_backtrace()[1]['function'];
        $hideStatus = ($calledFrom == 'nodeTypeCommand' || $calledFrom == 'componentCommand') ? true : false;
        $this->outputStatus($hideStatus);

    }

    /**
     * Create a single template file
     *
     * @param string $name The name of the file
     * @param string $packageKey
     * @param bool $subFolder
     * @param string $configType
     * @param string $suffix
     * @param bool $force
     */
    public function templateCommand(string $configType, string $name, string $packageKey = '', bool $subFolder = false , string $suffix = '', bool $force = false) {
        $packageKey = $this->resolvePackageKey($packageKey);
        $dynamicConfig = call_user_func([$this, 'get' . $configType . 'Config']);

        $targetPath = (isset($dynamicConfig['template']['targetPath'])) ? $dynamicConfig['template']['targetPath'] : '';
        $templatePath = (isset($dynamicConfig['template']['templatePath'])) ? $dynamicConfig['template']['templatePath'] : 'resource://Vectoholic.NeosCli/Private/Templates/Generator/View';
        $suffixFromConfig = (isset($dynamicConfig['template']['suffix'])) ? $dynamicConfig['template']['suffix'] : '';
        $suffix = ($suffix === '') ? $suffixFromConfig : $suffix;
        $this->generatorService->generateTemplate($name, $packageKey, $targetPath, $subFolder, $suffix, $templatePath, $force);
        $calledFrom = debug_backtrace()[1]['function'];
        $hideStatus = ($calledFrom == 'nodeTypeCommand' || $calledFrom == 'componentCommand') ? true : false;

        $this->outputStatus($hideStatus);

    }

    /**
     * Create a single javascript file
     *
     * @param string $name The name of the file
     * @param string $packageKey
     * @param bool $subFolder
     * @param string $configType
     * @param string $suffix
     * @param bool $force
     */
    public function javascriptCommand(string $configType, string $name, string $packageKey = '', bool $subFolder = false ,$suffix = '', bool $force = false) {
        $packageKey = $this->resolvePackageKey($packageKey);

        $dynamicConfig = call_user_func([$this, 'get' . $configType . 'Config']);

        $targetPath = (isset($dynamicConfig['javascript']['targetPath'])) ? $dynamicConfig['javascript']['targetPath'] : '';
        $templatePath = (isset($this->nodeTypeConfig['javascript']['templatePath'])) ? $this->nodeTypeConfig['javascript']['templatePath'] : 'resource://Vectoholic.NeosCli/Private/Templates/Generator/Assets';
        $suffixFromConfig = (isset($dynamicConfig['javascript']['suffix'])) ? $dynamicConfig['javascript']['suffix'] : '';
        $suffix = ($suffix === '') ? $suffixFromConfig : $suffix;
        $this->generatorService->generateJavascript($name, $packageKey, $targetPath, $subFolder, $suffix, $templatePath, $force);

        $calledFrom = debug_backtrace()[1]['function'];
        $hideStatus = ($calledFrom == 'nodeTypeCommand' || $calledFrom == 'componentCommand') ? true : false;

        $this->outputStatus($hideStatus);
    }

    /**
     * Create a single styles file
     *
     * @param string $name The name of the file
     * @param string $packageKey
     * @param bool $subFolder
     * @param string $configType
     * @param string $suffix
     * @param bool $force
     */
    public function stylesheetCommand(string $configType, string $name, string $packageKey = '', bool $subFolder = false, $suffix = '.css', bool $force = false) {
        $packageKey = $this->resolvePackageKey($packageKey);
        $dynamicConfig = call_user_func([$this, 'get' . $configType . 'Config']);

        $targetPath = (isset($dynamicConfig['styles']['targetPath'])) ? $dynamicConfig['styles']['targetPath'] : '';
        $templatePath = (isset($dynamicConfig['styles']['templatePath'])) ? $dynamicConfig['styles']['templatePath'] : 'resource://Vectoholic.NeosCli/Private/Templates/Generator/Assets';
        $suffixFromConfig = (isset($dynamicConfig['styles']['suffix'])) ? $dynamicConfig['styles']['suffix'] : '';
        $suffix = ($suffix === '') ? $suffixFromConfig : $suffix;
        $this->generatorService->generateStylesheet($name, $packageKey, $targetPath, $subFolder, $suffix, $templatePath, $force);

        $calledFrom = debug_backtrace()[1]['function'];
        $hideStatus = ($calledFrom == 'nodeTypeCommand' || $calledFrom == 'componentCommand') ? true : false;
        $this->outputStatus($hideStatus);
    }

    /**
     * Creating translation files
     *
     * @param string $name The name of the file
     * @param string $packageKey
     * @param string $configType
     * @param string $sourceLanguageKey
     * @param array $targetLanguageKeys
     * @param string $targetPath
     * @param string $suffix
     * @param bool $force
     */
    public function translationCommand(string $configType, string $name, string $packageKey = '',string $sourceLanguageKey = '', array $targetLanguageKeys = [] , string $targetPath = '', $suffix = '.xlf', bool $force = false) {
        $packageKey = $this->resolvePackageKey($packageKey);
        $dynamicConfig = call_user_func([$this, 'get' . $configType . 'Config']);

        $sourceLanguageKey = ($sourceLanguageKey === '') ? array_shift($this->languages) : $sourceLanguageKey;
        $targetLanguageKeys = (empty($targetLanguageKeys)) ? $this->languages : $targetLanguageKeys;
        $targetPathFromConfig = (isset($dynamicConfig['translation']['targetPath'])) ? $dynamicConfig['translation']['targetPath'] : '';
        $targetPath = ($targetPath == '') ? $targetPathFromConfig : $targetPath;
        $templatePath = (isset($dynamicConfig['translation']['templatePath'])) ? $dynamicConfig['translation']['templatePath'] : 'resource://Vectoholic.NeosCli/Private/Templates/Generator/Assets';
        $suffixFromConfig = (isset($dynamicConfig['translation']['suffix'])) ? $dynamicConfig['translation']['suffix'] : '';
        $suffix = ($suffix === '') ? $suffixFromConfig : $suffix;
        $this->generatorService->generateTranslation($name, $packageKey, $sourceLanguageKey, $targetLanguageKeys, $templatePath, $targetPath, $suffix);

        $calledFrom = debug_backtrace()[1]['function'];
        $hideStatus = ($calledFrom == 'nodeTypeCommand' || $calledFrom == 'componentCommand') ? true : false;
        $this->outputStatus($hideStatus);
    }

    /**
     * Helper function to resolve the right PackageKey
     *
     * @param string $packageKey
     * @return string
     * @throws GeneratorService
     */
    protected function resolvePackageKey(string $packageKey) {
        $this->packageKey = (isset($this->packageKey)) ? $this->packageKey : '';
        return $this->generatorService->resolvePackageKey($this->packageKey, $packageKey);
    }

    /**
     * Helper function that outputs the file status
     *
     * @param bool $hideOutput
     */
    protected function outputStatus(bool $hideOutput = false) {

        if (!$hideOutput) {
            foreach ($this->generatorService->getGeneratedFiles() as $message => $status) {
                $this->outputLine('<%1$s>STATUS | %2$s <%1$s>', [$status, $message]);
            }
        }
    }

    /**
     * Getter of the nodeType config
     *
     * @return array
     */
    protected function getNodeTypeConfig() {
        return $this->nodeTypeConfig;
    }

    /**
     * Getter of the component config
     *
     * @return array
     */
    protected function getComponentConfig() {
        return $this->componentConfig;
    }







}
