<?php
namespace TJM\Tests\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TJM\Component\Console\Command\ContainerAwareCommand as Base;

class EchoWithArgsAndOptsCommand extends Base{
	static public $defaultName = 'test:echo:arg-opts';
	protected function configure(){
		$this
			->setName('test:echo:arg-opts')
			->setDescription("Echo with args and options.")
			->addArgument('write', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Value to write.', ['foo'])
			->addOption('one', '1', InputOption::VALUE_REQUIRED, 'Option one.')
			->addOption('two', '2', InputOption::VALUE_REQUIRED, 'Option two.')
		;
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$write = implode("\n", $input->getArgument('write'));
		if($input->getOption('one') !== null){
			$write .= "\n1: {$input->getOption('one')}";
		}
		if($input->getOption('two') !== null){
			$write .= "\n2: {$input->getOption('two')}";
		}
		$output->writeln($write);
	}
}
