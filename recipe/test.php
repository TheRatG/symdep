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
 * Update project code
 */
task('test:update_code', function (InputInterface $input) {
    $basePath = config()->getPath();
    $repository = get('repository', false);

    if (false === $repository) {
        throw new \RuntimeException('You have to specify repository.');
    }
    $branch = get('branch', 'master');
    $releasePath = "$basePath/releases/$branch";

    env()->setReleasePath($releasePath);
    env()->set('is_new_release', false);

    cd($basePath);

    $branchExists = ('true' == RunHelper::exec("if [ -d \"$releasePath\" ]; then printf 'true'; fi"));
    if ($branchExists) {
        RunHelper::exec("cd $releasePath && git pull origin $branch --quiet");
    } else {
        RunHelper::exec("git clone --recursive -q $repository --branch $branch $releasePath");
    }

})->desc('Updating code')
    ->option('branch', 'b', 'Project branch', false);

/**
 * Vendors
 */
task('test:vendors', function (InputInterface $input) {
    if (!$input->getOption('skip-vendors')) {
        $releasePath = env()->getReleasePath();

        cd($releasePath);
        $prod = get('env', 'dev');

        if (programExist('composer')) {
            $composer = 'composer';
        } else {
            run("curl -s http://getcomposer.org/installer | php");
            $composer = 'php composer.phar';
        }

        run("SYMFONY_ENV=$prod $composer install --verbose --prefer-dist --optimize-autoloader");
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
    'test:update_code',
    'deploy:shared',
    'deploy:writable_dirs',
    'deploy:assets',
    'test:vendors',
    'deploy:cache:warmup',
    'deploy:assetic:dump',
    'database:migrate',
    'deploy:end'
])->option('branch', 'b', 'Project branch', false)
    ->option('skip-vendors', null, 'Skip local:vendors task', false)
    ->desc('Deploy your project on test platform');