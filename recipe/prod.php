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
        $pattern = '/RELEASE-\d+/';
        if (!preg_match($pattern, $branch) && 'prod' === get('env')) {
            throw new \RuntimeException(
                sprintf('Invalid branch name "%s", the pattern is "%s"', $branch, $pattern)
            );
        }

        set('branch', $branch);
        input()->setOption('branch', $branch);

        // Symfony shared files
        set('shared_files', ['app/config/parameters.yml', 'app/config/_secret.yml']);
        set('dump_assets', true);
        set('release_info', true);
        set('symdep_log_enable', true);
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


task(
    'cleanup:release-branches',
    function() {
        \TheRat\SymDep\ProductionReleaser::getInstance()->deleteReleaseBranches(get('keep_releases'));
    }
);
after('cleanup', 'cleanup:release-branches');
