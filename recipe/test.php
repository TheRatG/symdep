<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\RunHelper;

require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../../../deployer/deployer/recipe/symfony.php';

aliasTask('test:start', 'deploy:start');
task('test:parameters', function () {
    $branch = get('branch', 'master');

    set('env', 'test');
    if ('master' == $branch) {
        set('env', 'prod');
        set('envReal', 'test');
    }

    // Symfony shared dirs
    set('shared_dirs', ['web/uploads']);

    // Symfony shared files
    set('shared_files', ['app/config/parameters.yml', 'app/config/_secret.yml']);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs', 'web/uploads']);

    //Doctrine
    set('auto_migrate', true);
    set('doctrine_clear_cache', true);

    RunHelper::setRemote(true);

})->desc('Preparing deploy parameters');
aliasTask('test:prepare', 'deploy:prepare');
task('test:update_code', function (InputInterface $input) {
    $basePath = config()->getPath();
    $repository = get('repository', false);

    if (false === $repository) {
        throw new \RuntimeException('You have to specify repository.');
    }
    $branch = $input->getOption('branch', get('branch', 'master'));
    $releasePath = "$basePath/releases/$branch";

    env()->setReleasePath($releasePath);
    env()->set('is_new_release', false);

    cd($basePath);
    if (directoryExists($releasePath)) {
        RunHelper::exec("cd $releasePath && git pull origin $branch --quiet");
    } else {
        RunHelper::exec("cd $releasePath && git clone --recursive -q $repository --branch $branch $releasePath");
    }
})->desc('Updating code')
    ->option('branch', 'b', 'Project branch', false);
aliasTask('test:shared', 'deploy:shared');
aliasTask('test:writable_dirs', 'deploy:writable_dirs');
aliasTask('test:assets', 'deploy:assets');
task('test:vendors', function () {
    $releasePath = env()->getReleasePath();
    cd($releasePath);
    $prod = get('env', 'prod');
    if (programExist('composer')) {
        $composer = 'composer';
    } else {
        RunHelper::exec("php -r \"readfile('https://getcomposer.org/installer');\" | php");
        $composer = 'php composer.phar';
    }
    $options = get('composer_install_options', '--prefer-dist --optimize-autoloader --quiet');
    RunHelper::exec("SYMFONY_ENV=$prod $composer install $options");
})->desc('Installing vendors');
task('test:cache', function () {
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
task('test:assetic', function () {
    $prod = get('env', 'prod');
    console("assetic:dump --no-debug --env=$prod");
    console("assets:install --symlink --env=$prod");
})->desc('Dumping assetic and install assets');
aliasTask('test:migrate', 'database:migrate');
aliasTask('test:symlink', 'deploy:symlink');
aliasTask('test:cleanup', 'cleanup');
aliasTask('test:end', 'deploy:end');

/**
 * Main task
 */
task('test', [
    'test:start',
    'test:parameters',
    'test:prepare',
    'test:update_code',
    'test:shared',
    'test:writable_dirs',
    'test:assets',
    'test:vendors',
    'test:cache',
    'test:assetic',
    'test:migrate',
    'test:symlink',
    'test:cleanup',
    'test:end',
])
    ->option('branch', 'b', 'Project branch', false)
    ->desc('Deploy your test project');