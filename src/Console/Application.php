<?php
namespace TheRat\SymDep\Console;

use Deployer\Console\Application as BaseApplication;
use KevinGH\Amend\Command;
use KevinGH\Amend\Helper;

/**
 * Class Application
 *
 * @package TheRat\SymDep\Console
 */
class Application extends BaseApplication
{
    /**
     * @return array|\Symfony\Component\Console\Command\Command[]
     */
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        if (class_exists('\KevinGH\\Amend\\Command')) {
            $commands[] = $this->selfUpdateCommand();
        }

        return $commands;
    }

    /**
     * @return Command
     */
    protected function selfUpdateCommand()
    {
        $selfUpdate = new Command('self-update');
        $selfUpdate->setDescription('Updates symdep.phar to the latest version');
        $selfUpdate->setManifestUri('https://raw.githubusercontent.com/TheRatG/symdep/gh-pages/manifest.json');

        return $selfUpdate;
    }
}
