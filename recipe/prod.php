<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\RunHelper;

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../../../deployer/deployer/recipe/symfony.php';

task('prod:set_env', function () {
    set('env', 'prod');

    // Symfony shared dirs
    set('shared_dirs', ['app/logs', 'app/sessions', 'web/uploads']);

    // Symfony shared files
    set('shared_files', ['app/config/parameters.yml']);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs']);

    //Doctrine
    set('auto_migrate', false);
    set('doctrine_clear_cache', true);

    RunHelper::setRemote(true);

})->desc('Preparing deploy parameters');

/**
 * Vendors
 */
task('prod:vendors', function (InputInterface $input) {
    if (!$input->getOption('skip-vendors')) {
        $releasePath = env()->getReleasePath();

        cd($releasePath);
        $prod = get('env', 'dev');

        if (programExist('composer')) {
            $composer = 'composer';
        } else {
            run("php -r \"readfile('https://getcomposer.org/installer');\" | php");
            $composer = 'php composer.phar';
        }

        $options = get('composer_install_options', '--no-dev --prefer-dist --optimize-autoloader --quiet');
        run("SYMFONY_ENV=$prod $composer install $options");
    }
})->option('skip-vendors', null, 'Skip local:vendors task', false)
    ->desc('Installing vendors');

/**
 * Warm up cache
 */
task('prod:cache:warmup', function () {
    $releasePath = env()->getReleasePath();
    $cacheDir = env()->get('cache_dir', "$releasePath/app/cache");

    $prod = get('env', 'dev');

    RunHelper::exec("php $releasePath/app/console cache:clear --no-warmup --env=$prod");

    if (get('doctrine_clear_cache', false)) {
        RunHelper::exec("$releasePath/app/console doctrine:cache:clear-metadata --env=$prod");
        RunHelper::exec("$releasePath/app/console doctrine:cache:clear-query --env=$prod");
        RunHelper::exec("$releasePath/app/console doctrine:cache:clear-result --env=$prod");
    }

    RunHelper::exec("$releasePath/app/console cache:warmup --env=$prod");

    RunHelper::exec("chmod -R g+w $cacheDir");
})->desc('Clear and warming up cache');

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
    'prod:vendors',
    'prod:cache:warmup',
    'deploy:assetic:dump',
    'database:migrate',
    'deploy:symlink',
    'cleanup',
    'deploy:end'
])->option('branch', 'b', 'Project branch', false)
    ->option('skip-vendors', null, 'Skip local:vendors task', false)
    ->desc('Deploy your project');