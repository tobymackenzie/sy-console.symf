<?php
namespace TJM\Component\Console;

use ReflectionClass;
use ReflectionObject;
use SplFileInfo;
use Symfony\Component\Console\Application as Base;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use TJM\Component\Console\DependencyInjection\ConsoleExtension;
use TJM\Component\DependencyInjection\Loader\MultiPathLoader;

class Application extends Base implements ContainerAwareInterface{
	public function __construct($config = null){
		parent::__construct();

		if($config){
			$this->loadConfig($config);
		}
	}

	/*=====
	==Commands
	=====*/
	/*
	Method: buildClassNameForPath
	Runs `buildClassNameForFile()` on file given by path.
	*/
	protected function buildClassNameForPath($path, $ns = ''){
		return $this->buildClassNameForFile(new SplFileInfo($path), $ns);

	}

	/*
	Method: buildClassNameForFile
	Given a file containing a PSR class, returns the class name.  Because the class name can't be inferred only from the path, a namespace should be passed in if the class is in a namespace, or it will be assumed to not have one.
	*/
	public function buildClassNameForFile(SplFileInfo $file, $ns = ''){
		$className = $ns;
		if(method_exists($file, 'getRelativePath') && $relativePath = $file->getRelativePath()){
			$className .= '\\' . strtr($relativePath, '/', '\\');
		}
		if($ns){
			$className .= '\\';
		}
		$className .= $file->getBaseName('.php');
		return $className;
	}

	/*
	Property: rootNamespace
	Command namespace that is considered root for the application.  If defined, all commands in this namespace will be aliased without their namespace.
	*/
	protected $rootNamespace;
	public function getRootNamespace(){
		return $this->rootNamespace;
	}

	/*
	Method: addCommandsAtPath
	Add all commands at a path.  Commands must be php class-named files.
	Arguments:
		path(String): Path to directory to add commands from, or to a single command
		ns(String): Optional namespace base of class(es) at path.  Required if class(es) have namespaces.

	-@ http://stackoverflow.com/a/22411420
	-@ https://github.com/symfony/HttpKernel/blob/master/Bundle/Bundle.php#L174
	*/
	public function addCommandsAtPath($path, $ns = ''){
		if(is_dir($path)){
			$finder = new Finder();
			$finder->files()->name('*.php')->in($path);

			foreach($finder as $file){
				$className = $this->buildClassNameForFile($file, $ns);
				if(!class_exists($className)){
					require_once($file);
				}
				$this->addCommandForClassname($className);
			}
		}else{
			$className = $ns;
			if(!class_exists($className)){
				require_once($path);
			}
			$this->addCommandForClassname($className);
		}

		return $this;
	}

	/*
	Method: addCommandForClassname
	Adds an instance of a class name if that instance is a proper command.
	*/
	public function addCommandForClassname($className){
		$reflector = new ReflectionClass($className);
		if(
			$reflector->isSubclassOf('Symfony\\Component\\Console\\Command\\Command')
			&& !$reflector->isAbstract()
			&& !$reflector->getConstructor()->getNumberOfRequiredParameters()
		){
			$instance = $reflector->newInstance();

			//--if in root namespace, alias without that namespace
			if($this->getRootNamespace() && preg_match("/^{$this->getRootNamespace()}:(.*)$/", $instance->getName(), $matches)){
				$aliases = $instance->getAliases() ?: Array();
				$aliases[] = $matches[1];
				$instance->setAliases($aliases);
			}
			$this->add($instance);
		}

		return $this;
	}


	/*=====
	==Config
	=====*/
	//-! propably no longer needed becuase of loading through DI
	/*
	Property: config
	Current state of configuration.
	*/
	protected $config = Array();

	//-! propably no longer needed becuase of loading through DI
	/*
	Property: configSettings
	Settings of config for validating config keys / values.
	*/
	protected $configSettings;
	public function getConfigSettings(){
		if(!isset($this->configSettings)){
			$this->configSettings = new Configuration();
		}
		return $this->configSettings;
	}

	/*
	Property: configLoader
	Loader for loading config files
	*/
	protected $configLoader;
	public function getConfigLoader($path){
		if(!isset($this->configLoader)){
			$this->configLoader = new MultiPathLoader($this->getContainer());
		}
		return $this->configLoader;
	}

	//-! propably no longer needed becuase of loading through DI
	/*
	Property: configProcessor
	Processor for checking configuration.
	*/
	protected $configProcessor;
	public function getConfigProcessor(){
		if(!isset($this->configProcessor)){
			$this->configProcessor = new Processor();
		}
		return $this->configProcessor;
	}

	/*
	Method: loadConfig
	Load configuration, either from an array, or from files.
	Arguments:
		mapOrPath(Array|String):
	*/
	public function loadConfig($mapOrPath){
		if(is_string($mapOrPath)){
			$this->loadConfigFiles($mapOrPath);
		//-! propably no longer needed becuase of loading through DI
		//-! probably no longer works
		}else{
			$config = $this->processConfigData(Array($mapOrPath));
			$this->setConfig($config);
		}
	}

	/*
	Method: loadConfigFiles
	Load configuration from one or more files
	Arguments:
		paths(Array|String): path to a file or array of paths to mulitple files that will be loaded as configuration.
	*/
	public function loadConfigFiles($paths){
		if(is_string($paths) || is_callable($paths)){
			$paths = Array($paths);
		}
		foreach($paths as $path){
			$loader = $this->getConfigLoader($path);
			$loader->load($path);
		}

		$this->processConfig();

		return $this;
	}

	//-! propably no longer needed becuase of loading through DI
	/*
	Method: processConfigData
	Process an array of config data to make sure it properly matches configuration.
	*/
	public function processConfigData($configData){
		$processedConfig = $this->getConfigProcessor()->processConfiguration(
			$this->getConfigSettings()
			,$configData
		);
		return $processedConfig;
	}

	//-! propably no longer needed becuase of loading through DI
	//-! probably no longer works
	/*
	Method: setConfig
	Set configuration from an array.  Merges with existing settings unless 'replace' is true.  Make sure config has been processed with {processConfigData()} before passing into this method.
	Arguments:
		config(Array): array map of configuration parameters.  See {Configuration} for what configuration parameters there are.
		replace(Boolean): whether to replace existing config.  If false, will merge with existing config instead.
	*/
	protected function setConfig($config, $replace = false){
		if($replace){
			$this->config = $config;
		}else{
			$this->config = array_merge($this->config, $config);
		}
		$consoleConfig = $config;
		if(isset($consoleConfig['name'])){
			$this->getContainer()->setParameter('tjm_console.name', $consoleConfig['name']);
		}
		if(isset($consoleConfig['version'])){
			$this->getContainer()->setParameter('tjm_console.version', $consoleConfig['version']);
		}
		if(isset($consoleConfig['rootNamespace']) && !isset($this->rootNamespace)){
			$this->getContainer()->setParameter('tjm_console.rootNamespace', $consoleConfig['rootNamespace']);
		}
		if(isset($consoleConfig['commands']) && is_array($consoleConfig['commands'])){
			$this->getContainer()->setParameter('tjm_console.commands', $consoleConfig['commands']);
		}

		$this->processConfig();

		return $this;
	}

	/*
	Method: processConfig
	Process configuration as set in container parameters.
	*/
	public function processConfig(){
		$container = $this->getContainer();
		$container->compile();

		if($container->hasParameter('tjm_console.name')){
			$this->setName($container->getParameter('tjm_console.name'));
		}
		if($container->hasParameter('tjm_console.version')){
			$this->setVersion($container->getParameter('tjm_console.version'));
		}
		if($container->hasParameter('tjm_console.rootNamespace')){
			$this->rootNamespace = $container->getParameter('tjm_console.rootNamespace');
		}
		if($container->hasParameter('tjm_console.commands')){
			foreach($container->getParameter('tjm_console.commands') as $key=> $value){
				if(is_numeric($key)){
					$this->addCommandForClassname($value);
				}else{
					$this->addCommandsAtPath($value, $key);
				}
			}
		}
	}

	/*=====
	==Container
	=====*/
	/*
	Property: container
	Dependency injection service container
	*/
	protected $container;
	public function getContainer(){
		if(!isset($this->container)){
			$this->container = new ContainerBuilder();
			$this->container->registerExtension(new ConsoleExtension());
		}
		return $this->container;
	}
	public function setContainer(ContainerInterface $container = null){
		$this->container = $container;
		return $this;
	}
}
