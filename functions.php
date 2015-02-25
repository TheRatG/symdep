<?php
use Symfony\Component\Console\Input\InputInterface;

function runConsoleCommand($command)
{
    $releasePath = env()->getReleasePath();
    $env = get('env', false);

    if (!$env) {
        throw new \RuntimeException('"--env" is now defined');
    }

    return run("php $releasePath/app/console $command --env=$env --no-debug");
}

function getCurrentBranch(InputInterface $input = null)
{
    $branch = get('branch', false);
    if (!$branch) {
        $branch = $input->getOption('branch');
        if (!$branch) {
            $branch = trim(run('git rev-parse --abbrev-ref HEAD'));
        }
        set('branch', $branch);
    }
    return $branch;
}