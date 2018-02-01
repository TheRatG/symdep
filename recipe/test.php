<?php

namespace Deployer;

use TheRat\SymDep\FileHelper;

task(
    'properties',
    function () {
        if (has('local_branch') && has('branch') && has('deploy_path_original')) {
            return;
        }

        set('keep_releases', 2);
        set(
            'composer_options',
            '{{composer_action}} --verbose --prefer-dist --no-progress --no-interaction --optimize-autoloader'
        );

        // Deploy branch
        $branch = input()->getOption('branch');
        $localBranch = runLocally('if [ -d .git ]; then git rev-parse --abbrev-ref HEAD; fi;');
        if (!empty($branch) && !empty($localBranch) && $branch != $localBranch) {
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
        } else {
            set('clear_paths', []);
        }
        set('symfony_env', $env);

        set('deploy_path_original', parse('{{deploy_path}}'));
        set('deploy_path', parse('{{deploy_path_original}}/releases/') . strtolower(get('branch')));
        set('deploy_path_current_master', parse('{{deploy_path_original}}/releases/master/current'));

        set('shared_files', ['.env', 'config/_secret.yaml']);
        set('copy_files', ['shared/.env']);
        set('dump_assets', true);
    }
);

task('deploy:prepare', function () {
    // Check if shell is POSIX-compliant
    try {
        cd(''); // To run command as raw.
        $result = run('echo $0');
        if ($result == 'stdin: is not a tty') {
            throw new \RuntimeException(
                "Looks like ssh inside another ssh.\n" .
                "Help: http://goo.gl/gsdLt9"
            );
        }
    } catch (\RuntimeException $e) {
        $formatter = Deployer::get()->getHelper('formatter');

        $errorMessage = [
            "Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.",
            "Usually, you can change your shell to bash by running: chsh -s /bin/bash",
        ];
        write($formatter->formatBlock($errorMessage, 'error', true));

        throw $e;
    }
});

task(
    'test:prepare',
    function () {
        run('if [ ! -d {{deploy_path}} ]; then mkdir -p {{deploy_path}}; fi');

        // Check for existing /current directory (not symlink)
        $result = (bool)run('if [ ! -L {{deploy_path}}/current ] && [ -d {{deploy_path}}/current ]; then echo 1; fi');
        if ($result) {
            throw new \RuntimeException('There already is a directory (not symlink) named "current" in ' . get('deploy_path') . '. Remove this directory so it can be replaced with a symlink for atomic deployments.');
        }

        // Create metadata .dep dir.
        run("cd {{deploy_path}} && if [ ! -d .dep ]; then mkdir .dep; fi");

        // Create releases dir.
        run("cd {{deploy_path}} && if [ ! -d releases ]; then mkdir releases; fi");

        // Create shared dir.
        run("cd {{deploy_path}} && if [ ! -d shared ]; then mkdir shared; fi");

        $releaseMasterPath = parse('{{deploy_path_original}}/releases/master');
        if (FileHelper::dirExists($releaseMasterPath)) {
            foreach (get('copy_files') as $name) {
                $name = parse($name);
                if (DIRECTORY_SEPARATOR === substr($name, 0, 1)) {
                    writeln(sprintf('<error>Copy file "%s" must be relative</error>', $name));
                    continue;
                }
                $src = $releaseMasterPath . DIRECTORY_SEPARATOR . $name;
                $dst = parse('{{deploy_path}}') . DIRECTORY_SEPARATOR . $name;
                if (FileHelper::fileExists($src)) {
                    FileHelper::copyFile($src, $dst);
                } else {
                    writeln($src . ' skipped');
                }
            }
        }
    }
);
before('deploy:lock', 'test:prepare');

/**
 * Delete useless branches, which no in remote repository
 */
task(
    'drop-branches',
    function () {
        if ('test' != get('build_type')) {
            throw new \RuntimeException('This command only for "test" build type');
        }
        $path = get('deploy_path_original') . '/releases';
        $localBranches = explode("\n", run("ls $path"));
        run("cd {{deploy_path_current_master}}; git fetch && git fetch -p");
        $remoteBranches = explode("\n", run("cd {{deploy_path_current_master}} && {{bin/git}} ls-remote"));
        array_walk(
            $remoteBranches,
            function (&$item) {
                $item = trim($item);
                $item = substr($item, strrpos($item, '/') + 1);
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
                if (isVerbose()) {
                    if (askConfirmation("Do you want delete: $full")) {
                        run($cmd);
                    } else {
                        writeln(sprintf('Remove of "%s" skipped', $full));
                    }
                } else {
                    run($cmd);
                }
            }
        }
    }
);

