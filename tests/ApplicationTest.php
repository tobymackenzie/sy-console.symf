<?php
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\ApplicationTester;
use TJM\Component\Console\Application;
use TJM\Tests\Command\EchoWithArgsAndOptsCommand;
use TJM\Tests\Command\ThrowErrorCommand;

class ApplicationTest extends TestCase{
	// public function setUp(){ //-! uncomment for PHP < 7.0
	public function setUp(): void{
		require_once(__DIR__ . '/Command/EchoTestCommand.php');
		require_once(__DIR__ . '/Command/EchoFooCommand.php');
		require_once(__DIR__ . '/Command/EchoWithArgsAndOptsCommand.php');
		require_once(__DIR__ . '/Command/ThrowErrorCommand.php');
	}
	public function testErrorInCommand(){
		$app = new Application(__DIR__ . '/config/application.loadCommandsByDirPath.yml');
		$app->setAutoExit(false);
		$app->setCatchExceptions(false);
		$this->expectException(Exception::class);
		$this->expectExceptionMessage(ThrowErrorCommand::EXCEPTION_MESSAGE);
		$app->run(new ArrayInput(array('command'=> 'test:throw-error')));
	}
	public function testNoConfig(){
		$app = new Application();
		$this->assertEquals('UNKNOWN', $app->getName(), 'name should be set to default.');
		$this->assertEquals('UNKNOWN', $app->getVersion(), 'version should be set to default.');
		$this->assertEquals(null, $app->getRootNamespace(), 'rootNamespace should be set to default.');
	}
	public function testAppConfig(){
		$app = new Application(__DIR__ . '/config/application.appConfig.yml');
		$this->assertEquals('Test', $app->getName(), 'name should be set from configuration.');
		$this->assertEquals('0.1', $app->getVersion(), 'version should be set from configuration.');
		$this->assertEquals('foo', $app->getRootNamespace(), 'rootNamespace should be set from configuration.');
	}
	public function testArrayConfig(){
		$app = new Application(array(
			'commands'=> array(
				new ThrowErrorCommand(),
			),
			'rootNamespace'=> 'foo',
			'name'=> 'Test',
			'version'=> '0.1',
		));
		$this->assertEquals('Test', $app->getName(), 'name should be set from configuration.');
		$this->assertEquals('0.1', $app->getVersion(), 'version should be set from configuration.');
		$this->assertEquals('foo', $app->getRootNamespace(), 'rootNamespace should be set from configuration.');
		$this->assertTrue($app->has('test:throw-error'), 'command should be added.');
	}
	public function testParametersConfig(){
		$app = new Application(__DIR__ . '/config/application.parametersConfig.yml');
		$this->assertEquals(realpath(__DIR__), realpath($app->getContainer()->getParameter('paths.tests')), '"paths.tests" parameter should be set from config.');
		$this->assertEquals('foo', $app->getContainer()->getParameter('test'), '"test" parameter should be set from config.');
	}
	public function testServicesConfig(){
		$app = new Application(__DIR__ . '/config/application.servicesConfig.yml');
		$this->assertTrue($app->getContainer()->has('test'), 'App container should have a "test" service.');
		$testService = $app->getContainer()->get('test');
		$this->assertTrue(is_object($testService), '"test" service should be an object.');
		$this->assertEquals('TJM\Console\Tests\Service\Tst', get_class($testService), '"test" service should be of correct class.');
	}
	public function testLoadCommandsByClassName(){
		$app = new Application(__DIR__ . '/config/application.loadCommandsByClassName.yml');
		$this->assertTrue($app->has('test:echo:test'), 'EchoTestCommand should be loaded.');
		$this->assertTrue($app->has('test:echo:foo'), 'EchoFooCommand should be loaded.');

	}
	public function testLoadCommandsByDirPath(){
		$app = new Application(__DIR__ . '/config/application.loadCommandsByDirPath.yml');
		$this->assertTrue($app->has('test:echo:test'), 'EchoTestCommand should be loaded.');
		$this->assertTrue($app->has('test:echo:foo'), 'EchoFooCommand should be loaded.');
	}
	public function testLoadCommandsByFilePath(){
		$app = new Application(__DIR__ . '/config/application.loadCommandsByFilePath.yml');
		$this->assertTrue($app->has('test:echo:test'), 'EchoTestCommand should be loaded.');
		$this->assertTrue($app->has('test:echo:foo'), 'EchoFooCommand should be loaded.');
	}
	public function testLoadCommandsByServices(){
		//-! for symfony 3+ only
		if(class_exists('Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass')){
			$app = new Application(__DIR__ . '/config/application.loadCommandsByServices.yml');
			$this->assertTrue($app->has('test:echo:test'), 'EchoTestCommand should be loaded.');
			$this->assertTrue($app->has('test:echo:foo'), 'EchoFooCommand should be loaded.');
		}else{
			$this->markTestSkipped("This Symfony version doesn't support loading commands as services in the nice fashion that Symfony 3.3+ do.");
		}
	}

	//==default command
	public function testSimpleDefaultCommand(){
		$app = new Application(__DIR__ . '/config/application.loadCommandsByFilePath.yml');
		$app->setAutoExit(false);
		$app->setDefaultCommand('test:echo:foo');
		$tester = new ApplicationTester($app);
		$tester->run(array());
		$this->assertEquals("foo\n", $tester->getDisplay());
	}
	public function testSingleDefaultCommand(){
		$app = new Application(__DIR__ . '/config/application.single-command.yml');
		$app->setAutoExit(false);
		$tester = new ApplicationTester($app);
		$tester->run(array());
		$this->assertEquals("foo\n", $tester->getDisplay());
	}
	public function testSingleDefaultCommandArg(){
		$app = new Application(__DIR__ . '/config/application.single-command.yml');
		$app->setAutoExit(false);
		$tester = new ApplicationTester($app);
		$tester->run(array('write'=> ['a', 'b']));
		$this->assertEquals("a\nb\n", $tester->getDisplay());
	}
	public function testSingleDefaultCommandArgOpt(){
		$app = new Application(__DIR__ . '/config/application.single-command.yml');
		$app->setAutoExit(false);
		$output = new BufferedOutput();
		$app->run(new StringInput('--one c --two d a b'), $output);
		$this->assertEquals("a\nb\n1: c\n2: d\n", $output->fetch());
	}

	//==stdin
	public function testStdinString(){
		$app = new Application(array(
			'commands'=> array(
				new EchoWithArgsAndOptsCommand(),
			),
			//--fake stdin
			'stdin'=> 'foo bar',
		));
		$app->setAutoExit(false);
		$tester = new ApplicationTester($app);
		$tester->run(array('test:echo:arg-opts'));
		$this->assertEquals("foo bar\n", $tester->getDisplay());
	}
	public function testStdinPipe(){
		//--fake stdin
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "foo bar b");
		rewind($stream);

		$app = new Application(array(
			'commands'=> array(
				new EchoWithArgsAndOptsCommand(),
			),
			'stdin'=> $stream,
		));
		$app->setAutoExit(false);
		$tester = new ApplicationTester($app);
		$tester->run(array('test:echo:arg-opts'));
		$this->assertEquals("foo bar b\n", $tester->getDisplay());
	}
	public function testStdinDefaultSingleCommand(){
		//--fake stdin
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "foo bar c");
		rewind($stream);

		$app = new Application(array(
			'commands'=> array(
				new EchoWithArgsAndOptsCommand(),
			),
			'defaultCommand'=> 'test:echo:arg-opts',
			'singleCommand'=> true,
			'stdin'=> $stream,
		));
		$app->setAutoExit(false);
		$tester = new ApplicationTester($app);
		$tester->run(array());
		$this->assertEquals("foo bar c\n", $tester->getDisplay());
	}
	public function testStdinDefaultCommand(){
		//--fake stdin
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, "foo bar d");
		rewind($stream);

		$app = new Application(array(
			'commands'=> array(
				new EchoWithArgsAndOptsCommand(),
			),
			'defaultCommand'=> 'test:echo:arg-opts',
			'stdin'=> $stream,
		));
		$app->setAutoExit(false);
		$tester = new ApplicationTester($app);
		$tester->run(array());
		$this->assertEquals("foo bar d\n", $tester->getDisplay());
	}
}
