<?php
namespace TheRat\SymDep\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateDeployCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('symdep:generate:deploy')
            ->setDescription('Generate deploy file')
            ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $root = $this->getApplication()->get('kernel')->getRootDir();
        $output->writeln($root);
    }
}
