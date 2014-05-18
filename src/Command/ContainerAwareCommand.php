<?php
namespace TJM\Component\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/*
Class: ContainerAwareCommand
Command aware of and having a dependency injection container.
*/

abstract class ContainerAwareCommand extends Command implements ContainerAwareInterface{
	/*
	Property: container
	Dependency injection container
	*/
	protected $container;
	protected function getContainer(){
		if(!isset($this->container) && $this->getApplication()){
			$this->setContainer($this->getApplication()->getContainer());
		}
		return $this->container;
	}
	public function setContainer(ContainerInterface $container = null){
		$this->container = $container;
		return $this;
	}
}
