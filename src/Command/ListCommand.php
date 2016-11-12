<?php
namespace TheRat\SymDep\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\ListCommand as ParentListCommand;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends ParentListCommand
{
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = new DescriptorHelper();

        $commands = $this->getApplication()->all();

        $availableCommands = [
            'deploy',
            'install',
            'configure',
        ];
        $newCommands = [];
        foreach ($commands as $name => $command) {
            if (in_array($name, $availableCommands)) {
                $newCommands[$name] = $command;
            }
        }
        $application = new Application();
        $application->addCommands($newCommands);

        $helper->describe(
            $output,
            $application,
            [
                'format' => $input->getOption('format'),
                'raw_text' => $input->getOption('raw'),
                'namespace' => $input->getArgument('namespace'),
            ]
        );
    }
}
