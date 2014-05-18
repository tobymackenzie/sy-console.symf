<?php
namespace TJM\Component\Console;

use ReflectionClass;
use Symfony\Component\Console\Application as Base;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use TJM\Component\Console\DependencyInjection\Configuration;
use TJM\Component\Console\DependencyInjection\ContainerBuilder;

// use Symfony\Component\Config\FileLocator;

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
				$className = $ns;
				if($relativePath = $file->getRelativePath()){
					$className .= '\\' . strtr($relativePath, '/', '\\');
				}
				$className .= '\\' . $file->getBaseName('.php');
				$this->addCommandForClassname($className);
			}
		}else{
			$this->addCommandForClassname($ns);
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
				$instance->setAliases(Array($matches[1]));
			}
			$this->add($instance);
		}

		return $this;
	}


	/*=====
	==Config
	=====*/
	/*
	Property: config
	Current state of configuration.
	*/
	protected $config = Array();

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
			$config = $this->loadConfigFiles($mapOrPath);
		}else{
			$config = $this->processConfigData(Array($mapOrPath));
		}
		$this->setConfig($config);
	}

	/*
	Method: loadConfigFiles
	Load configuration from one or more files
	Arguments:
		paths(Array|String): path to a file or array of paths to mulitple files that will be loaded as configuration.
	*/
	public function loadConfigFiles($paths){
		if(is_string($paths)){
			$paths = Array($paths);
		}
		$configData = Array();
		foreach($paths as $path){
			// $locater = new FileLocator(Array(__DIR__ . DIRECTORY_SEPARATOR . '_config'));
			// $loaderResolver = new LoaderResolver(Array());
			// $delegatingLoader = new DelegatingLoader($loaderResolver);
			// $configFiles = $locater->locate('config.yml');
			if(is_string($path) && pathinfo($path, PATHINFO_EXTENSION) === 'yml'){
				$configData[] = Yaml::parse($path);
			}
		}
		return $this->processConfigData($configData);
	}

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
		if(isset($config['parameters'])){
			$parameters = $config['parameters'];
			$container = $this->getContainer();
			foreach($parameters as $parameter=> $value){
				$container->setParameter($parameter, $value);
			}
		}
		if(isset($config['services'])){
			$services = $config['services'];
			$container = $this->getContainer();
			foreach($services as $id=> $service){
				$container->setServiceForConfig($id, $service);
			}
		}
		if(isset($config['tjm_console'])){
			$consoleConfig = $config['tjm_console'];
			if(isset($consoleConfig['name'])){
				$this->name = $consoleConfig['name'];
			}
			if(isset($consoleConfig['version'])){
				$this->version = $consoleConfig['version'];
			}
			if(isset($consoleConfig['rootNamespace']) && !isset($this->rootNamespace)){
				$this->rootNamespace = $consoleConfig['rootNamespace'];
			}
			if(isset($consoleConfig['commands']) && is_array($consoleConfig['commands'])){
				foreach($consoleConfig['commands'] as $key=> $value){
					if(is_numeric($key)){
						$this->addCommandForClassname($value);
					}else{
						$this->addCommandsAtPath($value, $key);
					}
				}
			}
		}

		return $this;
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
		}
		return $this->container;
	}
	public function setContainer(ContainerInterface $container = null){
		$this->container = $container;
		return $this;
	}
}
