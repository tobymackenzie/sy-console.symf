<?php
namespace TJM\Component\Console\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface{
	public function getConfigTreeBuilder(){
		$treeBuilder = new TreeBuilder('tjm_console');
		$rootNode = $treeBuilder->getRootNode();
		$rootNode->children()
			->scalarNode('defaultCommand')
			->end()
			->scalarNode('name')
				->cannotBeOverwritten()
				->isRequired()
			->end()
			->scalarNode('version')
				->cannotBeOverwritten()
				->defaultValue('0.0')
			->end()
			->scalarNode('rootNamespace')
				->defaultValue('')
			->end()
			->arrayNode('commands')
				->prototype('scalar')->end()
			->end()
			->booleanNode('singleCommand')
				->defaultFalse()
			->end()
		->end();
		return $treeBuilder;
	}
}
