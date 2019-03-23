<?php
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use TJM\Component\Console\Application;

class ApplicationTest extends TestCase{
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
		require_once(__DIR__ . '/Command/EchoTestCommand.php');
		require_once(__DIR__ . '/Command/EchoFooCommand.php');
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
}
