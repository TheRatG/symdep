<?php
namespace Deployer;

task(
    'properties',
    function () {
        if (has('branch')) {
            return;
        }

        // Deploy branch
        $branch = input()->getOption('branch');
        if (!$branch) {
            $branch = 'master';
        }
        set('branch', $branch);
        input()->setOption('branch', $branch);

        // Symfony shared files
        set('shared_files', ['app/config/parameters.yml', 'app/config/_secret.yml']);
        set('dump_assets', true);
    }
);

task(
    'prepare',
    function () {
        run('if [ ! -d {{deploy_path}} ]; then mkdir -p {{deploy_path}}; fi');

        // Check for existing /current directory (not symlink)
        $result = run(
            'if [ ! -L {{deploy_path}}/current ] && [ -d {{deploy_path}}/current ]; then echo true; fi'
        )->toBool();
        if ($result) {
            throw new \RuntimeException(
                'There already is a directory (not symlink) named "current" in '.get(
                    'deploy_path'
                ).'. Remove this directory so it can be replaced with a symlink for atomic deployments.'
            );
        }

        // Create releases dir.
        run("cd {{deploy_path}} && if [ ! -d releases ]; then mkdir releases; fi");

        // Create shared dir.
        run("cd {{deploy_path}} && if [ ! -d shared ]; then mkdir shared; fi");
    }
);
before('deploy:lock', 'prepare');
