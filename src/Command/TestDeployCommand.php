<?php
namespace TheRat\SymDep\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestDeployCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('symdep:test')
            ->setDescription('Build application test');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln($this->getName());
    }
}
