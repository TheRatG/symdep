<?php
use TheRat\SymDep\Helper\Shell;
use TheRat\SymDep\Helper\ShellExec;
use TheRat\SymDep\SymDep;

require_once __DIR__ . '/../../../deployer/deployer/recipe/symfony.php';

SymDep::aliasTask('prod:start', 'deploy:start');
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

    ShellExec::setRemote(true);

})->desc('Preparing deploy parameters');
SymDep::aliasTask('prod:prepare', 'deploy:prepare');
SymDep::aliasTask('prod:update_code', 'deploy:update_code');
SymDep::aliasTask('prod:shared', 'deploy:shared');
SymDep::aliasTask('prod:writable_dirs', 'deploy:writable_dirs');
SymDep::aliasTask('prod:assets', 'deploy:assets');
task('prod:vendors', function () {
    $releasePath = env()->getReleasePath();
    cd($releasePath);
    $prod = get('env', 'prod');
    if (SymDep::programExist('composer')) {
        $composer = 'composer';
    } else {
        ShellExec::run("php -r \"readfile('https://getcomposer.org/installer');\" | php");
        $composer = 'php composer.phar';
    }
    $options = get('composer_install_options', '--no-dev --prefer-dist --optimize-autoloader --quiet');
    ShellExec::run("SYMFONY_ENV=$prod $composer install $options");
})->desc('Installing vendors');
task('prod:cache', function () {
    $releasePath = env()->getReleasePath();
    $cacheDir = env()->get('cache_dir', "$releasePath/app/cache");
    ShellExec::run("chmod -R g+w $cacheDir");

    Shell::touch("$releasePath/app/config/_secret.yml");

    SymDep::console("cache:clear --no-warmup");
    if (get('doctrine_clear_cache', false)) {
        SymDep::console("doctrine:cache:clear-metadata");
        SymDep::console("doctrine:cache:clear-query");
        SymDep::console("doctrine:cache:clear-result");
    }
    SymDep::console("cache:warmup");
})->desc('Clear and warming up cache');
task('prod:assetic', function () {
    $prod = get('env', 'prod');
    SymDep::console("assetic:dump --no-debug");
    SymDep::console("assets:install --symlink");
})->desc('Dumping assetic and install assets');
SymDep::aliasTask('prod:migrate', 'database:migrate');
SymDep::aliasTask('prod:symlink', 'deploy:symlink');
SymDep::aliasTask('prod:cleanup', 'cleanup');
SymDep::aliasTask('prod:end', 'deploy:end');

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
