<?php
namespace TheRat\SymDep\Recipe;

$basePath = realpath(__DIR__ . '/../../../');
require_once $basePath . '/therat/symdep/functions.php';
require_once $basePath . '/deployer/deployer/recipe/symfony.php';

task('prod:set_env', function () {
    set('env', 'prod');

    // Symfony shared dirs
    set('shared_dirs', ['app/logs', 'app/sessions', 'web/uploads']);

    // Symfony shared files
    set('shared_files', ['app/config/parameters.yml']);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs']);

    //Doctrine
    set('doctrine_auto_migrate', true);
    set('doctrine_clear_cache', true);

})->desc('Preparing deploy parameters');

/**
 * Main task
 */
task('deploy', [
    'prod:set_env',
    'deploy:start',
    'deploy:prepare',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable_dirs',
    'deploy:assets',
    'deploy:vendors',
    'deploy:cache:warmup',
    'deploy:assetic:dump',
    'database:migrate',
    'deploy:symlink',
    'cleanup',
    'deploy:end'
])->desc('Deploy your project');