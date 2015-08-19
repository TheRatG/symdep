<?php
namespace TheRat\SymDep\Console;

use Deployer\Console\Application as BaseApplication;
use KevinGH\Amend\Command;

class Application extends BaseApplication
{
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = $this->selfUpdateCommand();
        return $commands;
    }

    protected function selfUpdateCommand()
    {
        $selfUpdate = new Command('self-update');
        $selfUpdate->setDescription('Updates symdep.phar to the latest version');
        $selfUpdate->setManifestUri('https://raw.githubusercontent.com/TheRatG/symdep/gh-pages/manifest.json');
        return $selfUpdate;
    }
}
