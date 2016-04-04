<?php
/**
 * Preparing server for deployment.
 */
task(
    'project-update:properties',
    function () {

        // Composer install --no-dev
        env('composer_no_dev', input()->getOption('composer-no-dev'));

        // Symfony shared files
        set('shared_files', []);

        // Environment vars
        $env = \TheRat\SymDep\getBuildType();
        env('env_real', $env);
        env('no_debug', false);
        if ('test' == $env && 'master' == env('branch')) {
            $env = 'prod';
            env('no_debug', true);
        }
        env('env_vars', "SYMFONY_ENV=$env");
        env('env', $env);

        env('release_path', env('deploy_path'));
        env('symfony_console', '{{release_path}}/'.trim(get('bin_dir'), '/').'/console');
        cd('{{release_path}}');

        env('lock_dir', env('deploy_path'));
        env('current_path', env('release_path'));
    }
)->desc('Preparing server for deploy');

/**
 * Update project code
 */
task(
    'project-update:update_code',
    function () {
        $localBranch = env('local_branch');
        $branch = env('branch');

        if (empty($localBranch)) {
            $localBranch = runLocally('git rev-parse --abbrev-ref HEAD')->toString();
        }

        if (empty($branch)) {
            $branch = $localBranch;
        }

        $repository = get('repository');
        $res = trim(run("git ls-remote $repository $(git symbolic-ref HEAD)")->toString());

        if ($res) {
            $msg = sprintf(
                'Local "%s" and input "%s" branches are different! There will be merge.',
                $localBranch,
                $branch
            );
            if ($branch != $localBranch && !askConfirmation($msg)) {
                throw  new \RuntimeException('Deploy canceled');
            }

            run("git pull origin $branch 2>&1");
        } else {
            writeln("<comment>Remote $branch not found</comment>");
        }
    }
)->desc('Updating code');
