<?php
namespace TheRat\SymDep\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LocalDeployCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('symdep:local')
            ->setDescription('Build application locally');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln($this->getName());
    }
}
