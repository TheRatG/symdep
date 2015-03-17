<?php
use TheRat\SymDep\Helper\RunHelper;

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../../../deployer/deployer/recipe/symfony.php';

aliasTask('prod:start', 'deploy:start');
task('prod:parameters', function () {
    set('env', 'prod');

    // Symfony shared dirs
    set('shared_dirs', ['app/logs', 'app/sessions', 'web/uploads']);

    // Symfony shared files
    set('shared_files', ['app/config/parameters.yml', 'app/config/_secret.yml']);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs', 'web/uploads']);

    //Doctrine
    set('auto_migrate', false);
    set('doctrine_clear_cache', true);

    RunHelper::setRemote(true);

})->desc('Preparing deploy parameters');
aliasTask('prod:prepare', 'deploy:prepare');
aliasTask('prod:update_code', 'deploy:update_code');
aliasTask('prod:shared', 'deploy:shared');
aliasTask('prod:writable_dirs', 'deploy:writable_dirs');
aliasTask('prod:assets', 'deploy:assets');
task('prod:vendors', function () {
    $releasePath = env()->getReleasePath();
    cd($releasePath);
    $prod = get('env', 'prod');
    if (programExist('composer')) {
        $composer = 'composer';
    } else {
        RunHelper::exec("php -r \"readfile('https://getcomposer.org/installer');\" | php");
        $composer = 'php composer.phar';
    }
    $options = get('composer_install_options', '--no-dev --prefer-dist --optimize-autoloader --quiet');
    RunHelper::exec("SYMFONY_ENV=$prod $composer install $options");
})->desc('Installing vendors');
task('prod:cache', function () {
    $releasePath = env()->getReleasePath();
    $cacheDir = env()->get('cache_dir', "$releasePath/app/cache");
    RunHelper::exec("chmod -R g+w $cacheDir");

    $prod = get('env', 'prod');
    console("cache:clear --no-warmup --env=$prod");
    if (get('doctrine_clear_cache', false)) {
        console("doctrine:cache:clear-metadata --env=$prod");
        console("doctrine:cache:clear-query --env=$prod");
        console("doctrine:cache:clear-result --env=$prod");
    }
    console("cache:warmup");
})->desc('Clear and warming up cache');
task('prod:assetic', function () {
    $prod = get('env', 'prod');
    console("assetic:dump --no-debug --env=$prod");
    console("assets:install --symlink --env=$prod");
})->desc('Dumping assetic and install assets');
aliasTask('prod:migrate', 'database:migrate');
aliasTask('prod:symlink', 'deploy:symlink');
aliasTask('prod:cleanup', 'cleanup');
aliasTask('prod:end', 'deploy:end');

/**
 * Main task
 */
task('prod', [
    'prod:start',
    'prod:parameters',
    'prod:prepare',
    'prod:update_code',
    'prod:shared',
    'prod:writable_dirs',
    'prod:assets',
    'prod:vendors',
    'prod:cache',
    'prod:assetic',
    'prod:migrate',
    'prod:symlink',
    'prod:cleanup',
    'prod:end',
])
    ->option('branch', 'b', 'Project branch', false)
    ->desc('Deploy your project');