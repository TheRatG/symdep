<?php
use Deployer\Deployer;

require_once 'recipe/common.php';

// Symfony shared dirs
set('shared_dirs', ['app/cache', 'app/logs', 'web/uploads']);

// Symfony shared files
set('shared_files', ['app/config/parameters.yml']);

// Symfony writable dirs
set('writable_dirs', ['app/cache', 'app/logs', 'web/uploads']);

// Assets
set('assets', ['web/css', 'web/images', 'web/js']);

// Auto migrate
set('auto_migrate', true);

//Doctrine cache clear
set('doctrine_cache_clear', true);

set('writable_use_sudo', false);

// Environment vars
env('env_vars', 'SYMFONY_ENV=prod');
env('env', 'prod');

// Adding support for the Symfony3 directory structure
set('bin_dir', 'app');
set('var_dir', 'app');

/**
 * Default arguments and options.
 */
if (!Deployer::get()->getConsole()->getUserDefinition()->hasArgument('branch')) {
    argument('branch', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Release branch', 'master');
}

/**
 * Preparing server for deployment.
 */
task('deploy-on-prod:prepare:env', function () {

    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');

})->desc('Preparing server for deploy');

after('deploy:prepare', 'deploy-on-prod:prepare:env');

task('deploy-on-prod:update_code', function () {
    $releasePath = env('release_path');
    $repository = get('repository');
    $branch = env('branch');

    if (\TheRat\SymDep\dirExists($releasePath)) {
        run("cd $releasePath && git pull origin $branch --quiet");
    } else {
        run("mkdir -p $releasePath");
        run("cd $releasePath && git clone -b $branch --depth 1 --recursive -q $repository $releasePath");
    }
})->desc('Updating code');

/**
 * Create cache dir
 */
task('deploy:create_cache_dir', function () {
    // Set cache dir
    env('cache_dir', '{{release_path}}/' . trim(get('var_dir'), '/') . '/cache');

    // Remove cache dir if it exist
    run('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');

    // Create cache dir
    run('mkdir -p {{cache_dir}}');

    // Set rights
    run("chmod -R g+w {{cache_dir}}");
})->desc('Create cache dir');


/**
 * Normalize asset timestamps
 */
task('deploy-on-prod:assets', function () {
    $assets = array_map(function ($asset) {
        return "{{release_path}}/$asset";
    }, get('assets'));

    $time = date('Ymdhi.s');

    foreach ($assets as $dir) {
        if (\TheRat\SymDep\dirExists($dir)) {
            run("find $dir -exec touch -t $time {} ';' &> /dev/null || true");
        }
    }
})->desc('Normalize asset timestamps');


/**
 * Dump all assets to the filesystem
 */
task('deploy-on-prod:assetic:dump', function () {

    run('{{symfony_console}} assetic:dump --env={{env}} --no-debug');

})->desc('Dump assets');


/**
 * Warm up cache
 */
task('deploy-on-prod:cache:warmup', function () {

    run('{{symfony_console}} cache:warmup  --env={{env}} --no-debug');
    run('{{symfony_console}} assets:install --env={{env}} --no-debug');

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('deploy-on-prod:database:migrate', function () {
    if (get('auto_migrate')) {
        run('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-debug --no-interaction');
    }
})->desc('Migrate database');

/**
 * Doctrine cache clear database
 */
task('deploy-on-prod:database:cache-clear', function () {
    if (get('doctrine_cache_clear')) {
        run('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}} --no-debug');
        run('{{symfony_console}} doctrine:cache:clear-query --env={{env}} --no-debug');
        run('{{symfony_console}} doctrine:cache:clear-result --env={{env}} --no-debug');
    }
})->desc('Doctrine cache clear');

/**
 * Main task
 */
task('deploy-on-prod', [
    'deploy:prepare',
    'deploy:update_code',
    'deploy:create_cache_dir',
    'deploy:shared',
    'deploy:writable',
    'deploy-on-prod:assets',
    'deploy:vendors',
    'deploy-on-prod:assetic:dump',
    'deploy-on-prod:cache:warmup',
    'deploy-on-prod:database:migrate',
    'deploy:symlink',
    'deploy-on-prod:database:cache-clear',
    'cleanup',
])->desc('Deploy your project on "prod"');

after('deploy-on-prod', 'success');
