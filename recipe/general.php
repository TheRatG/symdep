<?php

namespace Deployer;

use Symfony\Component\Console\Input\InputOption;
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
        return run('which node');
    }
);
set(
    'bin/npm',
    function () {
        return run('which npm');
    }
);
set(
    'user',
    function () {
        return trim(run('whoami'));
    }
);
set('symfony_console', 'cd {{release_path}} && {{bin/php}} {{bin/console}}');
set('doctrine_migrate', true);
set('doctrine_cache_clear', true);
set('lock_wait', true);
set('lock_timeout', 15);
set('lock_filename', '{{deploy_path}}/deploy.lock');
set('release_info', false);
set('shared_dirs', ['var/logs', 'var/sessions', 'public/uploads', 'public/media']);

set('symdep_log_enable', false);
set(
    'symdep_log_dir',
    function () {
        return dirname(parse('{{deploy_file}}')) . '/var/logs/';
    }
);

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
task(
    'database:migrate',
    function () {
        if (get('doctrine_migrate')) {
            run(
                '{{bin/php}} {{bin/console}} doctrine:migrations:migrate {{console_options}} --allow-no-migration'
            );
        } else {
            writeln('Doctrine migrations skipped');
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
                    'date' => trim(run('date -u')),
                    'user' => trim(runLocally('whoami')),
                    'server' => trim(runLocally('uname -a')),
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
 * Clear Cache
 */
task('deploy:cache:clear', function () {
    run('{{bin/php}} {{bin/console}} cache:clear {{console_options}} --no-warmup');
})->desc('Clear cache');

/**
 * Warm up cache
 */
task('deploy:cache:warmup', function () {
    run('{{bin/php}} {{bin/console}} cache:warmup {{console_options}}', ['timeout' => 600]);
})->desc('Warm up cache');

/**
 * Doctrine cache clear database
 */
desc('Doctrine cache clear');
task(
    'database:cache-clear',
    function () {
        if (get('doctrine_cache_clear')) {
            run('{{bin/php}} {{bin/console}} doctrine:cache:clear-metadata {{console_options}}');
            run('{{bin/php}} {{bin/console}} doctrine:cache:clear-query {{console_options}}');
            run('{{bin/php}} {{bin/console}} doctrine:cache:clear-result {{console_options}}');
        }
    }
);

task(
    'deploy:secret_config',
    function () {
        if (!FileHelper::fileExists('{{release_path}}/config/_secret.yml')) {
            run('touch {{release_path}}/config/_secret.yml');
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
            ReleaseInfo::getInstance()->run();
        }
    }
)->desc('Release info');

task(
    'release-info-after',
    function () {
        if (get('release_info') && FileHelper::dirExists(parse('{{deploy_path}}/current'))) {
            ReleaseInfo::getInstance()->showIssues();
        }
    }
)->desc('Release info');

// -------------

task(
    'deploy',
    [
        'properties',
        'deploy:prepare',
        'release-info-before',
        'install-before',
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
        'deploy:cache:clear',
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
