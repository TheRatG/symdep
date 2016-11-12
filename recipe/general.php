<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputOption;
use TheRat\SymDep\DeploySection;
use TheRat\SymDep\FileHelper;
use TheRat\SymDep\Locker;

/**
 * Default arguments and options.
 */
option('lock-wait', 'w', InputOption::VALUE_NONE, 'Release lock');

set(
    'bin/node',
    function () {
        return run('which node')->toString();
    }
);
set(
    'bin/npm',
    function () {
        return run('which npm')->toString();
    }
);
set(
    'user',
    function () {
        return trim(run('whoami')->toString());
    }
);
set('symfony_console', 'cd {{release_path}} && {{env_vars}} {{bin/php}} {{bin/console}}');
set('doctrine_migrate', false);
set('doctrine_cache_clear', true);
set('lock_wait', true);
set('lock_timeout', 15);
set('lock_filename', '{{deploy_path}}/deploy.lock');

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
/**
 * Migrate database
 */
task(
    'database:migrate',
    function () {
        if (get('doctrine_migrate')) {
            run(
                '{{env_vars}} {{bin/php}} {{bin/console}} doctrine:migrations:migrate {{console_options}} --allow-no-migration'
            );
        }
    }
)->desc('Migrate database');
task(
    'deploy:lock',
    function () {
        $lockWait = get('lock_wait');
        $filename = get('lock_filename');
        $locker = new Locker($filename, get('lock_timeout'));
        $needLock = true;
        if ($locker->isLocked()) {
            if ($lockWait) {
                writeln($locker->__toString());
                $needLock = askConfirmation('Force deploy');
            } else {
                $needLock = false;
            }
        }
        if ($needLock) {
            $locker->lock(
                [
                    'date' => trim(run('date -u')->toString()),
                    'user' => trim(runLocally('whoami')->toString()),
                    'server' => trim(runLocally('uname -a')->toString()),
                ]
            );
            if (isVerbose()) {
                writeln(sprintf('Create lock file "%s"', $filename));
            }
        } else {
            throw new \RuntimeException('Deploy process locked');
        }
    }
);
task(
    'deploy:unlock',
    function () {
        $locker = new Locker(get('lock_filename'), get('lock_timeout'));
        $locker->unlock();
    }
);

/**
 * Doctrine cache clear database
 */
desc('Doctrine cache clear');
task(
    'database:cache-clear',
    function () {
        if (get('doctrine_cache_clear')) {
            run('{{env_vars}} {{bin/php}} {{bin/console}} doctrine:cache:clear-metadata {{console_options}}');
            run('{{env_vars}} {{bin/php}} {{bin/console}} doctrine:cache:clear-query {{console_options}}');
            run('{{env_vars}} {{bin/php}} {{bin/console}} doctrine:cache:clear-result {{console_options}}');
        }
    }
);

task('deploy:secret_config', function () {
    if (!FileHelper::fileExists('{{release_path}}/app/config/_secret.yml')) {
        run('touch {{release_path}}/app/config/_secret.yml');
    }
});

task(
    'drop-branches-from-test',
    [
        'properties',
        'drop-branches',
    ]
);
task(
    'drop-branches',
    function () {
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
after('install', 'deploy:secret_config');
after('install', 'deploy:create_cache_dir');
after('install', 'deploy:shared');
after('install', 'deploy:assets');
after('install', 'deploy:vendors');
after('install', 'install-after');

before('configure', 'configure-before');
before('configure', 'properties');
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
