<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\Shell;
use TheRat\SymDep\Helper\ShellExec;
use TheRat\SymDep\SymDep;

task('local:start', function () {
})->desc('Local start');
task('local:parameters', function () {
    set('env', 'dev');

    // Symfony shared dirs
    set('shared_dirs', ['app/logs', 'app/sessions', 'web/uploads']);

    // Symfony shared files
    set('shared_files', []);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs', 'web/uploads']);

    //Doctrine
    set('auto_migrate', true);
    set('doctrine_clear_cache', true);

    env()->setReleasePath(config()->getPath());
    ShellExec::setRemote(false);
})->desc('Preparing deploy parameters');
task('local:prepare', function () {
    $basePath = config()->getPath();
    Shell::mkdir($basePath . DIRECTORY_SEPARATOR . 'shared');
})->desc('Preparing server for deploy');
task('local:update_code', function (InputInterface $input) {
    $repository = get('repository', false);
    if (false === $repository) {
        throw new \RuntimeException('You have to specify repository.');
    }
    $basePath = config()->getPath();
    cd($basePath);
    $isGit = is_dir($basePath . '/.git');
    if ($isGit) {
        $branch = SymDep::getCurrentBranch($input);
        $res = ShellExec::run("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)");
        if ($res) {
            ShellExec::run("git pull origin $branch --quiet");
        } elseif (output()->isDebug()) {
            output()->writeln("<comment>Found local git branch. Pulling skipped.</comment>");
        }
    } elseif (output()->isDebug()) {
        output()->writeln("<comment>Update code skipped.</comment>");
    }
})->desc('Updating code')
    ->option('branch', 'b', 'Project branch', false);
task('local:writable_dirs', function () {
    $releasePath = env()->getReleasePath();
    cd($releasePath);
    // User specified writable dirs
    $dirs = (array)get('writable_dirs', []);
    $releasePath = env()->getReleasePath();
    foreach ($dirs as $dir) {
        // Create shared dir if does not exist
        Shell::mkdir($dir);
        ShellExec::run("chmod -R a+w $releasePath/$dir");
    }
})->desc('Make writable dirs');
task('local:shared', function () {
    $basePath = config()->getPath();
    $sharedPath = "$basePath/shared";
    $releasePath = env()->getReleasePath();

    // User specified shared directories
    $sharedDirs = (array)get('shared_dirs', []);

    foreach ($sharedDirs as $dir) {
        // Remove dir from source
        ShellExec::run("if [ -d $(echo $releasePath/$dir) ]; then rm -rf $releasePath/$dir; fi");

        // Create shared dir if does not exist
        ShellExec::run("mkdir -p $sharedPath/$dir");

        // Symlink shared dir to release dir
        ShellExec::run("ln -nfs $sharedPath/$dir $releasePath/$dir");
    }

    // User specified shared files
    $sharedFiles = (array)get('shared_files', []);

    foreach ($sharedFiles as $file) {
        // Create dir of shared file
        ShellExec::run("mkdir -p $sharedPath/" . dirname($file));

        // Touch shared file
        ShellExec::run("touch $sharedPath/$file");

        // Symlink shared file to release file
        ShellExec::run("ln -nfs $sharedPath/$file $releasePath/$file");
    }
})->desc('Creating symlinks for shared files');
task('local:vendors', function () {
    $releasePath = env()->getReleasePath();
    cd($releasePath);
    $prod = get('env', 'prod');
    if (SymDep::commandExist('composer')) {
        $composer = 'composer';
    } else {
        ShellExec::run("php -r \"readfile('https://getcomposer.org/installer');\" | php");
        $composer = 'php composer.phar';
    }
    $options = get('composer_install_options', '--optimize-autoloader --quiet');
    ShellExec::run("cd $releasePath; SYMFONY_ENV=$prod $composer install $options");
})->desc('Installing vendors');
task('local:cache', function () {
    $releasePath = env()->getReleasePath();
    $cacheDir = env()->get('cache_dir', "$releasePath/app/cache");
    ShellExec::run("chmod -R g+w $cacheDir");
    Shell::touch("$releasePath/app/config/_secret.yml");
    if (get('doctrine_clear_cache', false)) {
        SymDep::console("doctrine:cache:clear-metadata");
        SymDep::console("doctrine:cache:clear-query");
        SymDep::console("doctrine:cache:clear-result");
    }
    SymDep::console("cache:warmup");
})->desc('Clear and warming up cache');
task('local:assetic', function () {
    SymDep::console("assetic:dump --no-debug");
    SymDep::console("assets:install --symlink");
})->desc('Dumping assetic and install assets');
task('local:migrate', function () {
    $serverName = config()->getName();
    $run = get('auto_migrate', false);
    if (output()->isVerbose()) {
        $run = askConfirmation("Run migrations on $serverName server?", $run);
    }
    if ($run) {
        SymDep::console("doctrine:migrations:migrate --no-interaction");
    }
})->desc('Migrating database');
task('local:end', function () {
})->desc('Local end');

/**
 * Main task
 */
task('local', [
    'local:start',
    'local:parameters',
    'local:prepare',
    'local:update_code',
    'local:shared',
    'local:writable_dirs',
    'local:vendors',
    'local:cache',
    'local:assetic',
    'local:migrate',
    'local:end',
])
    ->option('branch', 'b', 'Project branch', false)
    ->desc('Deploy your project');
