<?php

namespace Deployer;

task('create_release_branch', function () {
    if (has('branch')) {
        return;
    }

    if (!input()->getOption('skip-branch')) {
        // Deploy branch
        $branch = input()->getOption('branch');
        if (!$branch && has('branch')) {
            $branch = get('branch');
        }
        if ('last' === strtolower($branch)) {
            $branch = \TheRat\SymDep\ProductionReleaser::getInstance()->getLastReleaseBranch();
        }

        if (!$branch) {
            $branch = \TheRat\SymDep\ProductionReleaser::getInstance()->createReleaseBranch();
            output()->writeln(sprintf('<info>Release branch "%s" was automatically created</info>', $branch));
        }

        set('branch', $branch);
        input()->setOption('branch', $branch);
    }
})->local();

task(
    'properties',
    function () {
        // Symfony shared files
        set('shared_files', ['.env', 'config/_secret.yaml']);
        set('dump_assets', true);
        set('release_info', true);
        set('symdep_log_enable', true);
    }
);

before('properties', 'create_release_branch');

task(
    'prepare',
    function () {
        run('if [ ! -d {{deploy_path}} ]; then mkdir -p {{deploy_path}}; fi');

        // Check for existing /current directory (not symlink)
        $result = (bool)run(
            'if [ ! -L {{deploy_path}}/current ] && [ -d {{deploy_path}}/current ]; then echo 1; fi'
        );
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


task(
    'cleanup:release-branches',
    function () {
        try {
            \TheRat\SymDep\ProductionReleaser::getInstance()->deleteReleaseBranches(get('keep_releases'));
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            writeln($e->getMessage());
        }
    }
)->local();
after('release-info-after', 'cleanup:release-branches');
