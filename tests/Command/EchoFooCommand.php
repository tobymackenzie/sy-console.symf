<?php
namespace TJM\Tests\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;

class EchoFooCommand extends Base{
	protected function configure(){
		$this
			->setName('test:echo:foo')
			->setDescription("Echo 'foo'.")
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$output->writeln('foo');
	}
}
