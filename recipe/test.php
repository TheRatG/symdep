<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\Shell;
use TheRat\SymDep\Helper\ShellExec;
use TheRat\SymDep\SymDep;

require_once __DIR__ . '/../../../deployer/deployer/recipe/symfony.php';

task('test:start', function () {
})->desc('Test start');
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
    $releasePath = $basePath . DIRECTORY_SEPARATOR . "releases" . DIRECTORY_SEPARATOR . $branch;

    env()->setReleasePath($releasePath);
    env()->set('is_new_release', false);

    cd($basePath);
    if (SymDep::dirExists($releasePath)) {
        ShellExec::run("cd $releasePath && git pull origin $branch --quiet");
    } else {
        ShellExec::run("cd $releasePath && git clone --recursive -q $repository --branch $branch $releasePath");
    }
})->desc('Updating code')
    ->option('branch', 'b', 'Project branch', 'master');
SymDep::aliasTask('test:shared', 'deploy:shared');
SymDep::aliasTask('test:writable_dirs', 'deploy:writable_dirs');
SymDep::aliasTask('test:assets', 'deploy:assets');
task('test:vendors', function () {
    $releasePath = env()->getReleasePath();
    cd($releasePath);
    $prod = get('env', 'prod');
    if (SymDep::commandExist('composer')) {
        $composer = 'composer';
    } else {
        ShellExec::run("php -r \"readfile('https://getcomposer.org/installer');\" | php");
        $composer = 'php composer.phar';
    }
    $options = get('composer_install_options', '--prefer-dist --optimize-autoloader --quiet');
    ShellExec::run("cd $releasePath; SYMFONY_ENV=$prod $composer install $options");
})->desc('Installing vendors');
task('test:cache', function () {
    $releasePath = env()->getReleasePath();
    $cacheDir = env()->get('cache_dir', "$releasePath/app/cache");
    ShellExec::run("chmod -R g+w $cacheDir");
    Shell::touch("$releasePath/app/config/_secret.yml");
    if (get('doctrine_clear_cache', false)) {
        SymDep::console("doctrine:cache:clear-metadata");
        SymDep::console("doctrine:cache:clear-query");
        SymDep::console("doctrine:cache:clear-result");
    }
    SymDep::console("cache:clear");
})->desc('Clear and warming up cache');
task('test:assetic', function () {
    SymDep::console("assetic:dump --no-debug");
    SymDep::console("assets:install --symlink");
})->desc('Dumping assetic and install assets');
SymDep::aliasTask('test:migrate', 'database:migrate');
SymDep::aliasTask('test:symlink', 'deploy:symlink');
SymDep::aliasTask('test:cleanup', 'cleanup');
task('test:end', function () {
})->desc('Test end');

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
    ->option('branch', 'b', 'Project branch', 'master')
    ->desc('Deploy your test project');
