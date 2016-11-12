<?php
namespace Deployer;

task(
    'properties',
    function () {
        // nix user
        set('user', 'vagrant');

        // Symfony build set
        set('env', '{{build_type}}');

        // Deploy branch
        $branch = input()->getOption('branch');
        $localBranch = runLocally('git rev-parse --abbrev-ref HEAD')->toString();
        if (!$branch) {
            $branch = $localBranch;
        }
        set('local_branch', $localBranch);
        set('branch', $branch);

        set('release_path', '{{deploy_path}}');
        set('current_path', '{{deploy_path}}');
    }
);

task(
    'deploy:update_code',
    function () {
        $localBranch = get('local_branch');
        $branch = get('branch');
        if (empty($localBranch)) {
            $localBranch = runLocally('{{bin/git}} rev-parse --abbrev-ref HEAD')->toString();
        }
        if (empty($branch)) {
            $branch = $localBranch;
        }
        $repository = get('repository');
        $res = trim(run("{{bin/git}} ls-remote $repository $(git symbolic-ref HEAD)")->toString());
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
