<?php
use TheRat\SymDep\SymDep;
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\ShellExec;

require_once __DIR__ . '/../../../deployer/deployer/recipe/symfony.php';

SymDep::aliasTask('test:start', 'deploy:start');
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

    ShellExec::setRemote(true);

})->desc('Preparing deploy parameters');
SymDep::aliasTask('test:prepare', 'deploy:prepare');
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
    if (SymDep::directoryExists($releasePath)) {
        ShellExec::run("cd $releasePath && git pull origin $branch --quiet");
    } else {
        ShellExec::run("cd $releasePath && git clone --recursive -q $repository --branch $branch $releasePath");
    }
})->desc('Updating code')
    ->option('branch', 'b', 'Project branch', false);
SymDep::aliasTask('test:shared', 'deploy:shared');
SymDep::aliasTask('test:writable_dirs', 'deploy:writable_dirs');
SymDep::aliasTask('test:assets', 'deploy:assets');
task('test:vendors', function () {
    $releasePath = env()->getReleasePath();
    cd($releasePath);
    $prod = get('env', 'prod');
    if (SymDep::programExist('composer')) {
        $composer = 'composer';
    } else {
        ShellExec::run("php -r \"readfile('https://getcomposer.org/installer');\" | php");
        $composer = 'php composer.phar';
    }
    $options = get('composer_install_options', '--prefer-dist --optimize-autoloader --quiet');
    ShellExec::run("SYMFONY_ENV=$prod $composer install $options");
})->desc('Installing vendors');
task('test:cache', function () {
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
task('test:assetic', function () {
    $prod = get('env', 'prod');
    SymDep::console("assetic:dump --no-debug");
    SymDep::console("assets:install --symlink");
})->desc('Dumping assetic and install assets');
SymDep::aliasTask('test:migrate', 'database:migrate');
SymDep::aliasTask('test:symlink', 'deploy:symlink');
SymDep::aliasTask('test:cleanup', 'cleanup');
SymDep::aliasTask('test:end', 'deploy:end');

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
