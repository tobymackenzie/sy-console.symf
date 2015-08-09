<?php
namespace TJM\Tests\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;

class EchoTestCommand extends Base{
	protected function configure(){
		$this
			->setName('test:echo:test')
			->setDescription("Echo 'test'.")
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$output->writeln('test');
	}
}
