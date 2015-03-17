<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\RunHelper;

require_once __DIR__ . '/../src/functions.php';

aliasTask('local:start', 'deploy:start');
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
    RunHelper::setRemote(false);
})->desc('Preparing deploy parameters');
task('local:prepare', function () {
    $basePath = config()->getPath();
    shMkdir($basePath);
    shMkdir('shared');
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
        $branch = getCurrentBranch($input);
        $res = RunHelper::exec("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)");
        if ($res) {
            RunHelper::exec("git pull origin $branch --quiet");
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
        shMkdir($dir);
        RunHelper::exec("chmod -R a+w $releasePath/$dir");
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
        RunHelper::exec("if [ -d $(echo $releasePath/$dir) ]; then rm -rf $releasePath/$dir; fi");

        // Create shared dir if does not exist
        RunHelper::exec("mkdir -p $sharedPath/$dir");

        // Symlink shared dir to release dir
        RunHelper::exec("ln -nfs $sharedPath/$dir $releasePath/$dir");
    }

    // User specified shared files
    $sharedFiles = (array)get('shared_files', []);

    foreach ($sharedFiles as $file) {
        // Create dir of shared file
        RunHelper::exec("mkdir -p $sharedPath/" . dirname($file));

        // Touch shared file
        RunHelper::exec("touch $sharedPath/$file");

        // Symlink shared file to release file
        RunHelper::exec("ln -nfs $sharedPath/$file $releasePath/$file");
    }
})->desc('Creating symlinks for shared files');
task('local:vendors', function () {
    $releasePath = env()->getReleasePath();
    cd($releasePath);
    $prod = get('env', 'prod');
    if (programExist('composer')) {
        $composer = 'composer';
    } else {
        RunHelper::exec("php -r \"readfile('https://getcomposer.org/installer');\" | php");
        $composer = 'php composer.phar';
    }
    $options = get('composer_install_options', '--optimize-autoloader --quiet');
    RunHelper::exec("SYMFONY_ENV=$prod $composer install $options");
})->desc('Installing vendors');
task('local:cache', function () {
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
task('local:assetic', function () {
    $prod = get('env', 'prod');
    console("assetic:dump --no-debug --env=$prod");
    console("assets:install --symlink --env=$prod");
})->desc('Dumping assetic and install assets');
task('local:migrate', function () {
    $releasePath = env()->getReleasePath();
    $prod = get('env', 'dev');
    $serverName = config()->getName();
    $run = get('auto_migrate', false);
    if (output()->isVerbose()) {
        $run = askConfirmation("Run migrations on $serverName server?", $run);
    }
    if ($run) {
        RunHelper::exec("$releasePath/app/console doctrine:migrations:migrate --env=$prod --no-interaction");
    }
})->desc('Migrating database');

/**
 * Main task
 */
task('prod', [
    'local:start',
    'local:parameters',
    'local:prepare',
    'local:update_code',
    'local:shared',
    'local:writable_dirs',
    'local:assets',
    'local:vendors',
    'local:cache',
    'local:assetic',
    'local:migrate',
    'local:end',
])
    ->option('branch', 'b', 'Project branch', false)
    ->desc('Deploy your project');