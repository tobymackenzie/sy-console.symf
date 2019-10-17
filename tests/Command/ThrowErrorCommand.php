<?php
namespace TJM\Tests\Command;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;

class ThrowErrorCommand extends Base{
	const EXCEPTION_MESSAGE = "Test throw";
	static public $defaultName = 'test:throw-error';
	protected function configure(){
		$this
			->setName('test:throw-error')
			->setDescription("Throw error.")
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		throw new Exception(self::EXCEPTION_MESSAGE);
	}
}
