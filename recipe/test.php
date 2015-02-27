<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\RunHelper;

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../../../deployer/deployer/recipe/symfony.php';

task('test:set_env', function () {
    set('env', 'test');

    // Symfony shared dirs
    set('shared_dirs', ['web/uploads']);

    // Symfony shared files
    set('shared_files', ['app/config/parameters.yml']);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs']);

    //Doctrine
    set('doctrine_auto_migrate', true);
    set('doctrine_clear_cache', true);

    RunHelper::setRemote(true);

})->desc('Preparing deploy parameters');

/**
 * Vendors
 */
task('test:vendors', function (InputInterface $input) {
    if (!$input->getOption('skip-vendors')) {
        $releasePath = env()->getReleasePath();

        cd($releasePath);
        $prod = get('env', 'dev');

        if (commandExist('composer')) {
            $composer = 'composer';
        } else {
            run("curl -s http://getcomposer.org/installer | php");
            $composer = 'php composer.phar';
        }

        run("SYMFONY_ENV=$prod $composer install --verbose --prefer-dist --optimize-autoloader --no-dev");
    }
})->option('skip-vendors', null, 'Skip local:vendors task', false)
    ->desc('Installing vendors');

/**
 * Main task
 */
task('test', [
    'test:set_env',
    'deploy:start',
    'deploy:prepare',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable_dirs',
    'deploy:assets',
    'test:vendors',
    'deploy:cache:warmup',
    'deploy:assetic:dump',
    'database:migrate',
    'deploy:symlink',
    'cleanup',
    'deploy:end'
])->option('branch', 'b', 'Project branch', false)
    ->option('skip-vendors', null, 'Skip local:vendors task', false)
    ->desc('Deploy your project on test platform');