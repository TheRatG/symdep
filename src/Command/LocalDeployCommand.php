<?php
namespace TheRat\SymDep\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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

        $dir = dirname(dirname(__DIR__));
        $cmd = "php $dir/deployer.phar deploy local";

        $output->writeln($cmd);

        $process = new Process($cmd);
        $process->setTimeout(0);
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > ' . $buffer;
            } else {
                echo $buffer;
            }
        });
    }
}
