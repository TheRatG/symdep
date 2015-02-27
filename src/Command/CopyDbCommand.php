<?php
namespace TheRat\SymDep\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CopyDbCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('symdep:copy-db')
            ->setDescription('Create copy db by branch name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        $params = [
            'driver' => $container->getParameter('database_driver'),
            'host' => $container->getParameter('database_host'),
            'port' => $container->getParameter('database_port'),
            'dbname' => $container->getParameter('database_name'),
            'user' => $container->getParameter('database_user'),
            'password' => $container->getParameter('database_password')
        ];

    }

    protected function getBranchName()
    {
        $rootPath = $this->getContainer()->getParameter('kernel.root_dir');
        $cmd = 'cd ' . $rootPath . ' && git rev-parse --abbrev-ref HEAD';
        $branchRef = exec($cmd, $output, $returnVar);
        if (!$returnVar) {
            throw new \RuntimeException('Cannot get branch name');
        }
        return $branchRef;
    }
}
