<?php
namespace TJM\Component\Console;

use Exception;
use ReflectionClass;
use ReflectionObject;
use SplFileInfo;
use Symfony\Component\Console\Application as Base;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TJM\Component\Console\DependencyInjection\ConsoleExtension;
use TJM\Component\DependencyInjection\Loader\MultiPathLoader;

class Application extends Base implements ContainerAwareInterface{
	protected $dispatcher;
	protected $runningCommand;
	protected $stdin;
	public function __construct($config = null){
		parent::__construct();

		if(is_array($config)){
			foreach($config as $key=> $value){
				switch($key){
					case 'commands':
						foreach($value as $commandKey=> $commandValue){
							$this->add($commandValue);
						}
					break;
					default:
						$setter = 'set' . ucfirst($key);
						if(method_exists($this, $setter)){
							$this->$setter($value);
						}else{
							$this->$key = $value;
						}
					break;
				}
			}
		}elseif($config){
			$this->loadConfig($config);
		}
		//-# `if` for unit testing
		if(empty($this->stdin)){
			$this->stdin = STDIN;
		}
		if(is_resource($this->stdin)){
			$stdin = $this->stdin;
			stream_set_blocking($stdin, 0);
			$this->stdin = stream_get_contents($this->stdin);
			stream_set_blocking($stdin, 1);
		}
	}

	//--override to support use in `doRun`
	public function setDispatcher(EventDispatcherInterface $dispatcher): void{
		$this->dispatcher = $dispatcher;
	}

	//--override to remove built in `-n` and `-q` short options
	protected function configureIO(InputInterface $input, OutputInterface $output){
		//--determine decoration
		if($input->hasParameterOption('--ansi', true)){
			$output->setDecorated(true);
		}elseif($input->hasParameterOption('--no-ansi', true)){
			$output->setDecorated(false);
		}

		//--determine interactivity
		if($input->hasParameterOption('--no-interaction', true)){
			$input->setInteractive(false);
		}elseif(function_exists('posix_isatty')){
			if($input instanceof StreamableInputInterface){
				$inputStream = $input->getStream();
			}else{
				$inputStream = null;
			}
			//-! for symfony < 4
			if(
				!$inputStream
				&& $this->getHelperSet()->has('question')
				&& method_exists($this->getHelperSet()->get('question'), 'getInputStream')
			){
				$inputStream = $this->getHelperSet()->get('question')->getInputStream(false);
			}
			if(!@posix_isatty($inputStream) && getenv('SHELL_INTERACTIVE') === false){
				$input->setInteractive(false);
			}
		}

		//--determine verbosity
		if($input->hasParameterOption('--quiet', true)){
			$shellVerbosity = -1;
		}else{
			if(
				$input->hasParameterOption('-vvv', true)
					|| $input->hasParameterOption('--verbose=3', true)
					|| 3 === $input->getParameterOption('--verbose', false, true)
			){
				$shellVerbosity = 3;
			}elseif($input->hasParameterOption('-vv', true)
				|| $input->hasParameterOption('--verbose=2', true)
				|| 2 === $input->getParameterOption('--verbose', false, true)
			){
				$shellVerbosity = 2;
			}elseif($input->hasParameterOption('-v', true)
				|| $input->hasParameterOption('--verbose=1', true)
				|| $input->hasParameterOption('--verbose', true)
				|| $input->getParameterOption('--verbose', false, true)
			){
				$shellVerbosity = 1;
			}
			if(!isset($shellVerbosity)){
				$shellVerbosity = (int) getenv('SHELL_VERBOSITY');
			}
		}
		switch($shellVerbosity){
			case -1:
				$output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
				$input->setInteractive(false);
			break;
			case 1:
				$output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
			break;
			case 2:
				$output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
			break;
			case 3:
				$output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);
			break;
		}
		putenv('SHELL_VERBOSITY='.$shellVerbosity);
		$_ENV['SHELL_VERBOSITY'] = $shellVerbosity;
		$_SERVER['SHELL_VERBOSITY'] = $shellVerbosity;
	}

	//--override this to remove built in `-h` short option
	public function doRun(InputInterface $input, OutputInterface $output){
		//--handle global parameters
		if($input->hasParameterOption(Array('--version', '-V'), true)){
			$output->writeln($this->getLongVersion());
			return 0;
		}
		$name = $this->getCommandName($input);
		if($input->hasParameterOption('--help', true)){
			if(!$name){
				$name = 'help';
				$input = new ArrayInput(Array('command_name'=> $this->defaultCommand));
			}else{
				$this->wantHelps = true;
			}
		}
		if(!$name){
			$isDefault = true;
			$name = $this->defaultCommand;
		}else{
			$isDefault = false;
		}

		//--get command
		try{
			$this->runningCommand = null;
			$command = $this->find($name);
		}catch(Exception $e){
		}catch(Throwable $e){
		}

		//--add stdin
		if($this->stdin){
			$stream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
			$input = (string) $input;
			if($isDefault || $this->isSingleCommand()){
				if($isDefault && !$input){
					$input = $name . ' ' . escapeshellarg($this->stdin);
				}else{
					$input = escapeshellarg($this->stdin) . ' ' . $input;
				}
			}else{
				$input = preg_replace('/^([^ ]+)/', '$1 ' . escapeshellarg($this->stdin), $input);
			}
			$input = new StringInput($input);
			if($stream){
				$input->setStream($stream);
			}
		}

		//--run
		if(isset($e)){
			if($this->dispatcher !== null){
				$event = new ConsoleErrorEvent($input, $output, $e);
				$this->dispatcher->dispatch(ConsoleEvents::ERROR, $event);
				$e = $event->getError();
				if($event->getExitCode() === 0){
					return 0;
				}
			}
			throw $e;
		}
		$this->runningCommand = $command;
		$exitCode = $this->doRunCommand($command, $input, $output);
		$this->runningCommand = null;

		return $exitCode;
	}

	//--override because other overridden commands use these private properties
	protected $defaultCommand = 'list';
	protected $singleCommand = false;
	public function isSingleCommand(): bool{
		return $this->singleCommand;
	}
	protected function getCommandName(InputInterface $input): ?string{
		return ($this->isSingleCommand() ? $this->defaultCommand : $input->getFirstArgument());
	}
	public function setDefaultCommand($name, $asSingleCommand = false): static{
		$this->defaultCommand = $name;
		if($asSingleCommand){
			$this->find($commandName);
			$this->singleCommand = true;
		}
		return $this;
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

	/*
	Method: getDefaultInputDefinition
	Like parent, but without shortcuts that we don't want to clobber, eg `-h`, `-n`, `-q`.  Might as well put my own spin on help text.
	*/
	protected function getDefaultInputDefinition(): InputDefinition{
		$opts = Array(
			new InputOption('--ansi', null, InputOption::VALUE_NONE, 'Force ANSI output coloring'),
			new InputOption('--no-ansi', null, InputOption::VALUE_NONE, 'Disable ANSI output coloring'),
			new InputOption('--help', null, InputOption::VALUE_NONE, 'Display command help'),
			new InputOption('--no-interaction', null, InputOption::VALUE_NONE, 'Disable interactive input'),
			new InputOption('--verbose', '-v|vv|vvv', InputOption::VALUE_NONE, 'Increase verbosity of output. Use two or three times to increase verbosity'),
			new InputOption('--quiet', null, InputOption::VALUE_NONE, 'Supress all output'),
			new InputOption('--version', '-V', InputOption::VALUE_NONE, 'Display version of application'),
		);
		if(!$this->isSingleCommand()){
			array_unshift($opts, new InputArgument('command', InputArgument::REQUIRED, 'Name of the command to run'));
		}
		return new InputDefinition($opts);
	}

	/*=====
	==Config
	=====*/


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


	/*
	Method: loadConfig
	Load configuration from files.
	Arguments:
		paths(Array|String):
	*/
	public function loadConfig($paths){
		$this->loadConfigFiles($paths);
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

	/*
	Method: processConfig
	Process configuration as set in container parameters.
	*/
	public function processConfig(){
		$container = $this->getContainer();
		$container->compile();
		//-! for symfony 3+ only
		if(class_exists('Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass')){
			$this->setCommandLoader($this->container->get('console.command_loader'));
		}

		if($container->hasParameter('tjm_console.defaultCommand')){
			$this->setDefaultCommand($container->getParameter('tjm_console.defaultCommand'));
		}
		if($container->hasParameter('tjm_console.name')){
			$this->setName($container->getParameter('tjm_console.name'));
		}
		if($container->hasParameter('tjm_console.version')){
			$this->setVersion($container->getParameter('tjm_console.version'));
		}
		if($container->hasParameter('tjm_console.rootNamespace')){
			$this->rootNamespace = $container->getParameter('tjm_console.rootNamespace');
		}
		if($container->hasParameter('tjm_console.singleCommand')){
			$this->singleCommand = $container->getParameter('tjm_console.singleCommand');
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
			//-! for symfony 3+ only
			if(class_exists('Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass')){
				$this->container->addCompilerPass(new AddConsoleCommandPass());
			}
			$this->container->registerExtension(new ConsoleExtension());
		}
		return $this->container;
	}
	public function setContainer(?ContainerInterface $container = null){
		$this->container = $container;
		return $this;
	}
}
