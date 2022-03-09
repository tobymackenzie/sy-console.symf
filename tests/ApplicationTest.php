<?php
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use TJM\Component\Console\Application;
use TJM\Tests\Command\ThrowErrorCommand;

class ApplicationTest extends TestCase{
	// public function setUp(){ //-! uncomment for PHP < 7.0
	public function setUp(): void{
		require_once(__DIR__ . '/Command/EchoTestCommand.php');
		require_once(__DIR__ . '/Command/EchoFooCommand.php');
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
		$this->assertEquals('TJM\Tests\Service\Test', get_class($testService), '"test" service should be of correct class.');
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
}
