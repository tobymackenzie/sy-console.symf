<?php
namespace TJM\Component\Console\DependencyInjection;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder as Base;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class ContainerBuilder extends Base{
	/*
	Method: setServiceForConfig
	Set a service based on a config array.

	-@ Symfony\Component\DependencyInjection\Loader\YamlFileLoader
	*/
	public function setServiceForConfig($id, $service){
		if(is_string($service) && strpos($service, '@') === 0){
			$this->setAlias($id, substr($service, 1));
			return;
		}elseif(isset($service['alias'])){
			$isPublic = (!array_key_exists('public', $service) || (Boolean) $service['public']);
			$this->setAlias(new Alias($service['alias'], $isPublic));
			return;
		}
		if(isset($service['parent'])){
			$definition = new DefinitionDecorator($service['parent']);
		}else{
			$definition = new Definition();
		}
		$defKeys = Array(
			'abstract'
			,'class'
			,'factory_class'
			,'factory_method'
			,'factory_service'
			,'file'
			,'public'
			,'scope'
			,'synthetic'
		);
		foreach($defKeys as $defKey){
			if(isset($service[$defKey])){
				$setter = "set";
				$setterPieces = explode('_', $defKey);
				foreach($setterPieces as $setterPiece){
					$setter .= ucfirst($setterPiece);
				}
				$definition->$setter($service[$defKey]);
			}
		}
		if(isset($service['arguments'])){
			$definition->setArguments($this->resolveServices($service['arguments']));
		}

		if(isset($service['properties'])){
			$definition->setProperties($this->resolveServices($service['properties']));
		}
		if(isset($service['calls'])){
			foreach($service['calls'] as $call){
				$args = isset($call[1]) ? $this->resolveServices($call[1]) : array();
				$definition->addMethodCall($call[0], $args);
			}
		}
		if(isset($service['configurator'])){
			if(is_string($service['configurator'])){
				$definition->setConfigurator($service['configurator']);
			}else{
				$definition->setConfigurator(array($this->resolveServices($service['configurator'][0]), $service['configurator'][1]));
			}
		}
		if(isset($service['tags'])){
			if(!is_array($service['tags'])){
				throw new InvalidArgumentException(sprintf('Parameter "tags" must be an array for service "%s" in %s.', $id, $file));
			}

			foreach($service['tags'] as $tag){
				if(!isset($tag['name'])){
					throw new InvalidArgumentException(sprintf('A "tags" entry is missing a "name" key for service "%s" in %s.', $id, $file));
				}
				$name = $tag['name'];
				unset($tag['name']);
				foreach($tag as $attribute => $value){
					if(!is_scalar($value)){
						throw new InvalidArgumentException(sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s" in %s.', $id, $name, $file));
					}
				}
				$definition->addTag($name, $tag);
			}
		}
		$this->setDefinition($id, $definition);

		return $this;
	}
}
