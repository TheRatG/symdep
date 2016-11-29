<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputOption;
use TheRat\SymDep\DeploySection;
use TheRat\SymDep\FileHelper;
use TheRat\SymDep\Locker;
use TheRat\SymDep\ReleaseInfo;

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
set('doctrine_migrate', true);
set('doctrine_cache_clear', true);
set('lock_wait', true);
set('lock_timeout', 15);
set('lock_filename', '{{deploy_path}}/deploy.lock');
set('release_info', false);
set('shared_dirs', ['var/logs', 'var/sessions', 'web/uploads', 'web/media']);

// helper tasks  ------------------------------
task(
    'properties',
    function () {
    }
)->desc('Prepare properties');
task(
    'install-before',
    function () {
    }
)->desc('Before install');
task(
    'install-after',
    function () {
    }
)->desc('After install');
task(
    'configure-before',
    function () {
    }
)->desc('Before configure');
task(
    'configure-after',
    function () {
    }
)->desc('After configure');
task(
    'link-before',
    function () {
    }
)->desc('Before link');
task(
    'link-after',
    function () {
    }
)->desc('after link');

/**
 * Symdep tasks ------------------------------
 */
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

task(
    'deploy:secret_config',
    function () {
        if (!FileHelper::fileExists('{{release_path}}/app/config/_secret.yml')) {
            run('touch {{release_path}}/app/config/_secret.yml');
        }
    }
);

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

task(
    'release-info-before',
    function () {
        if (get('release_info') && FileHelper::dirExists(parse('{{deploy_path}}/current'))) {
            $releaseInfo = new ReleaseInfo();
            $releaseInfo->run();
        }
    }
)->desc('Release info');

task(
    'release-info-after',
    function () {
        if (get('release_info') && FileHelper::dirExists(parse('{{deploy_path}}/current'))) {
            $releaseInfo = new ReleaseInfo();
            $releaseInfo->showIssues();
        }
    }
)->desc('Release info');

// -------------

task(
    'deploy',
    [
        'deploy:prepare',
        'release-info-before',
        'install-before',
        'properties',
        'deploy:lock',
        'deploy:release',
        'deploy:update_code',
        'deploy:clear_paths',
        'deploy:secret_config',
        'deploy:create_cache_dir',
        'deploy:shared',
        'deploy:assets',
        'deploy:vendors',
        'install-after',
        'configure-before',
        'deploy:assets:install',
        'deploy:assetic:dump',
        'database:cache-clear',
        'database:migrate',
        'deploy:cache:warmup',
        'configure-after',
        'link-before',
        'deploy:symlink',
        'deploy:unlock',
        'cleanup',
        'link-after',
        'release-info-after',
    ]
)->desc('Run deploy project, depend on --build-type=<[d]ev|[t]est|[p]rod>');
