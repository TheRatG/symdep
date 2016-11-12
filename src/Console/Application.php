<?php
namespace TheRat\SymDep\Console;

use Deployer\Component\PharUpdate\Console\Command as PharUpdateCommand;
use Deployer\Console\Application as DeployerApplication;
use Symfony\Component\Console\Command\HelpCommand;
use TheRat\SymDep\Command\ListCommand;

/**
 * Class Application
 *
 * @package TheRat\SymDep\Console
 */
class Application extends DeployerApplication
{
    /**
     * {@inheritdoc}
     */
    protected function getDefaultCommands()
    {
        $commands = [
            new HelpCommand(),
            new ListCommand(),
        ];
        $commands[] = $this->selfUpdateCommand();

        return $commands;
    }

    /**
     * @return PharUpdateCommand
     */
    private function selfUpdateCommand()
    {
        $selfUpdate = new PharUpdateCommand('self-update');
        $selfUpdate->setDescription('Updates symdep.phar to the latest version');
        $selfUpdate->setManifestUri('@manifest_url@');

        return $selfUpdate;
    }
}
