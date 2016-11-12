<?php
namespace Deployer;

use TheRat\SymDep\DeploySection;
use TheRat\SymDep\FileHelper;

task(
    'deploy',
    [
        'install',
        'configure',
        'link',
    ]
)->desc('Run deploy project, depend on --build-type=<[d]ev|[t]est|[p]rod>');
task(
    'properties',
    function () {
    }
)->desc('1. Prepare properties');
task(
    'install-before',
    function () {
    }
)->desc('2. Before install');
task(
    'install',
    function () {
        set('section', DeploySection::INSTALL);

        if (!FileHelper::fileExists('{{release_path}}/app/config/_secret.yml')) {
            run('touch {{release_path}}/app/config/_secret.yml');
        }
    }
)->desc('Deploy and prepare project files');
task(
    'install-after',
    function () {
    }
)->desc('4. After install');
task(
    'configure-before',
    function () {
    }
)->desc('5. Before configure');
task(
    'configure',
    function () {
        set('section', DeploySection::CONFIGURE);
    }
)->desc('Configure project, run project scripts');
task(
    'configure-after',
    function () {
    }
)->desc('7. After configure');
task(
    'link-before',
    function () {
    }
)->desc('8. Before link');
task(
    'link',
    function () {
        set('section', DeploySection::LINK);
    }
)->desc('Change symlinks');
task(
    'link-after',
    function () {
    }
)->desc('10. after link');

/**
 * Symdep tasks ------------------------------
 */

task(
    'deploy:check_connection',
    function () {
        // Check if shell is POSIX-compliant
        try {
            cd(''); // To run command as raw.
            $result = run('echo $0')->toString();
            if ($result == 'stdin: is not a tty') {
                throw new \RuntimeException(
                    "Looks like ssh inside another ssh.\n".
                    "Help: http://goo.gl/gsdLt9"
                );
            }
        } catch (\RuntimeException $e) {
            $errorMessage = [
                "Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.",
                "Usually, you can change your shell to bash by running: chsh -s /bin/bash",
            ];
            write(sprintf('<error>%s</error>', $errorMessage));

            throw $e;
        }
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

/**
 * Doctrine cache clear database
 */
desc('Doctrine cache clear');
task(
    'database:cache-clear',
    function () {
        run('{{env_vars}} {{bin/php}} {{bin/console}} doctrine:cache:clear-metadata {{console_options}}');
        run('{{env_vars}} {{bin/php}} {{bin/console}} doctrine:cache:clear-query {{console_options}}');
        run('{{env_vars}} {{bin/php}} {{bin/console}} doctrine:cache:clear-result {{console_options}}');
    }
);

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
        //TODO: check branch -r
        $remoteBranches = run("cd {{release_path}} && {{bin/git}} branch -r")->toArray();
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

/**
 * Configure deploy command list ------------------------------------------------------------
 */
before('properties', 'deploy:check_connection');
before('install', 'install-before');
before('install', 'properties');
after('install', 'deploy:lock');
after('install', 'deploy:release');
after('install', 'deploy:update_code');
after('install', 'deploy:create_cache_dir');
after('install', 'deploy:shared');
after('install', 'deploy:assets');
after('install', 'deploy:vendors');
after('install', 'install-after');

before('configure', 'configure-before');
before('configure', 'properties');
after('configure', 'deploy:vendors');
after('configure', 'deploy:assets:install');
after('configure', 'deploy:assetic:dump');
after('configure', 'database:cache-clear');
after('configure', 'database:migrate');
after('configure', 'deploy:cache:warmup');
after('configure', 'configure-after');

before('link', 'link-before');
before('link', 'properties');
after('link', 'deploy:symlink');
after('link', 'deploy:unlock');
after('link', 'cleanup');
after('link', 'link-after');
