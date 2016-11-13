<?php
namespace Deployer;

use Deployer\Task\Context;
use TheRat\SymDep\FileHelper;

task(
    'properties',
    function () {
        set('keep_releases', 2);
        set(
            'composer_options',
            '{{composer_action}} --verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader'
        );

        // Deploy branch
        $branch = input()->getOption('branch');
        $localBranch = runLocally('[ -d .git ] && git rev-parse --abbrev-ref HEAD')->toString();
        if (!empty($branch) && $branch != $localBranch) {
            $msg = sprintf(
                'Local branch "%s" does not equal "%s" remote, continue?',
                $localBranch,
                $branch
            );
            if (!askConfirmation($msg)) {
                throw new \RuntimeException('Deploy canceled');
            }
        }
        if (!$branch) {
            $branch = $localBranch;
        }
        set('local_branch', $localBranch);
        set('branch', $branch);
        input()->setOption('branch', $branch);

        $env = 'test';
        if ('master' === $branch) {
            $env = 'prod';
        }
        set('env', $env);
        set('env_vars', "SYMFONY_ENV=$env");

        if (!has('deploy_path_original')) {
            set('deploy_path_original', get('deploy_path'));
            set('deploy_path', parse('{{deploy_path_original}}/releases/{{branch}}'));
            set('deploy_path_current_master', parse('{{deploy_path_original}}/releases/master/current'));
        }

        set('copy_files', ['app/config/parameters.yml']);
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

        $currentMaster = get('deploy_path_current_master');
        if (FileHelper::dirExists($currentMaster)) {
            foreach (get('copy_files') as $name) {
                $name = parse($name);
                if (DIRECTORY_SEPARATOR !== $name) {
                    writeln(sprintf('<error>Copy file "%s" must be relative</error>', $name));
                    continue;
                }
                $src = $currentMaster.DIRECTORY_SEPARATOR.$name;
                $dst = get('release_name').DIRECTORY_SEPARATOR.$name;
                FileHelper::copyFile($src, $dst);
            }
        }
    }
);
before('deploy:lock', 'prepare');

/**
 * Delete useless branches, which no in remote repository
 */
task(
    'drop-branches',
    function () {
        if ('test' != get('build_type')) {
            throw new \RuntimeException('This command only for "test" build type');
        }
        $path = get('deploy_path').'/releases';
        $localBranches = run("ls $path")->toArray();
        run("cd {{deploy_path_current_master}}; git fetch && git fetch -p");
        $remoteBranches = run("cd {{current_path}} && {{bin/git}} branch -r")->toArray();
        array_walk(
            $remoteBranches,
            function (&$item) {
                $item = trim($item);
                $item = substr($item, strpos($item, '/') + 1);
                $item = explode(' ', $item)[0];
                $item = strtolower($item);
            }
        );
        $diff = array_diff($localBranches, $remoteBranches);
        if (isVerbose()) {
            writeln(sprintf('<info>Local dir: %s</info>', implode(', ', $localBranches)));
            writeln(sprintf('<info>Remote branches: %s</info>', implode(', ', $remoteBranches)));
            writeln(
                sprintf(
                    '<comment>Dir for delete: %s</comment>',
                    !empty($diff) ? implode(', ', $diff) : 'none'
                )
            );
        }
        foreach ($diff as $deleteDir) {
            $full = "$path/$deleteDir";
            if (FileHelper::dirExists($full)) {
                $cmd = sprintf('rm -rf %s', escapeshellarg($full));
                if (isVerbose() && askConfirmation("Do you want delete: $full")) {
                    run($cmd);
                } else {
                    run($cmd);
                }
            }
        }
    }
);
