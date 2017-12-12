<?php

namespace Deployer;

task(
    'properties',
    function () {
        if (has('local_branch') && has('branch')) {
            return;
        }

        // Symfony build set
        set('symfony_env', '{{build_type}}');

        // Symfony shared dirs
        set('shared_dirs', []);
        set('shared_files', []);
        set('clear_paths', []);

        // Deploy branch
        $branch = input()->getOption('branch');
        $localBranch = runLocally('git rev-parse --abbrev-ref HEAD');
        if (!$branch) {
            $branch = $localBranch;
        }
        set('local_branch', $localBranch);
        set('branch', $branch);
        input()->setOption('branch', $branch);

        set('release_path', '{{deploy_path}}');
        set('current_path', '{{deploy_path}}');
        set('composer_options', '{{composer_action}} --prefer-source --optimize-autoloader');
        set('dump_assets', true);
        // Set cache dir
        set('cache_dir', '{{release_path}}/' . trim(get('var_dir'), '/') . '/cache');
    }
);

task(
    'deploy:prepare',
    function () {
        // Create shared dir.
        run("cd {{deploy_path}} && if [ ! -d shared ]; then mkdir shared; fi");
    }
);

task(
    'deploy:update_code',
    function () {
        $localBranch = get('local_branch');
        $branch = get('branch');
        if (empty($localBranch)) {
            $localBranch = runLocally('{{bin/git}} rev-parse --abbrev-ref HEAD');
        }
        if (empty($branch)) {
            $branch = $localBranch;
        }
        $repository = get('repository');
        $res = trim(run("{{bin/git}} ls-remote $repository $(git symbolic-ref HEAD)"));
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
);

task(
    'deploy:create_cache_dir',
    function () {
        run('if [ ! -d "{{cache_dir}}" ]; then mkdir -p {{cache_dir}}; fi');
    }
);

task(
    'deploy:release',
    function () {
    }
);
task(
    'deploy:symlink',
    function () {
    }
);
task(
    'deploy:lock',
    function () {
    }
);
task(
    'deploy:unlock',
    function () {
    }
);
task(
    'cleanup',
    function () {
    }
);
