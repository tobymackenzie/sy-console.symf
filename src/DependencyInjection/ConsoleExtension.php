<?php
namespace TJM\Component\Console\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension as Base;
use Symfony\Component\DependencyInjection\ContainerBuilder as BaseContainerBuilder;
class ConsoleExtension extends Base{
	public function load(array $configs, BaseContainerBuilder $container){
		$configuration = new Configuration();
		$config = $this->processConfiguration($configuration, $configs);
		foreach($config as $key=> $value){
			$this->setParameter($container, $key, $value);
		}
	}

	public function setParameter($container, $key, $value){
		$container->setParameter($this->getNamespace() . ".{$key}", $value);
	}

	public function getNamespace(){
		return 'tjm_console';
	}
}
