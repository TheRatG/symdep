<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\RunHelper;

require_once __DIR__ . '/../src/functions.php';

task('local:set_env', function () {
    set('env', 'dev');

    // Symfony shared dirs
    set('shared_dirs', ['app/logs', 'app/sessions', 'web/uploads']);

    // Symfony shared files
    set('shared_files', []);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs']);

    //Doctrine
    set('auto_migrate', true);
    set('doctrine_clear_cache', false);

    //use in local:writable_dirs
    set('permission_method', 'chmod_bad');
    env()->setReleasePath(config()->getPath());

    RunHelper::setRemote(false);

})->desc('Preparing env variables');

/**
 * Preparing server for deployment.
 */
task('local:prepare', function () {
    $basePath = config()->getPath();

    // Check if base path exist.
    RunHelper::exec("if [ ! -d $(echo $basePath) ]; then mkdir $basePath; fi", true);

    // Create shared dir.
    RunHelper::exec("if [ ! -d \"shared\" ]; then mkdir shared; fi");
})->desc('Preparing server for deploy');

/**
 * Update project code
 */
task('local:update_code', function (InputInterface $input) {
    $basePath = config()->getPath();
    $repository = get('repository', false);

    if (false === $repository) {
        throw new \RuntimeException('You have to specify repository.');
    }

    cd($basePath);
    $isGit = is_dir($basePath . '/.git');
    if ($isGit) {
        $branch = getCurrentBranch($input);
        $res = RunHelper::exec("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)");
        if ($res) {
            RunHelper::exec("git pull origin $branch --quiet");
        } else {
            if (output()->isDebug()) {
                output()->writeln("<comment>Found local git branch. Pulling skipped.</comment>");
            }
        }
    } else {
        if (output()->isDebug()) {
            output()->writeln("<comment>Update code skipped.</comment>");
        }
    }
})->desc('Updating code')
    ->option('branch', 'b', 'Project branch', false);

/**
 * Make writable dirs
 */
task('local:writable_dirs', function () {
    $user = config()->getUser();
    $wwwUser = config()->getWwwUser();
    $permissionMethod = get('permission_method', 'acl');
    $releasePath = env()->getReleasePath();

    cd($releasePath);

    // User specified writable dirs
    $dirs = (array)get('writable_dirs', []);

    $commands = [];
    switch ($permissionMethod) {
        case 'acl':
            $run = RunHelper::exec("if which setfacl; then echo \"ok\"; fi");
            if (empty($run)) {
                writeln('<comment>Enable ACL support and install "setfacl"</comment>');
                return;
            }

            $commands = [
                'setfacl -R -m u:' . $user . ':rwX -m u:' . $wwwUser . ':rwX %s',
                'setfacl -dR -m u:' . $user . ':rwx -m u:' . $wwwUser . ':rwx %s'
            ];
            break;
        case 'chmod':
            $commands = [
                'chmod +a "' . $user . ' allow delete,write,append,file_inherit,directory_inherit" %s',
                'chmod +a "' . $wwwUser . ' allow delete,write,append,file_inherit,directory_inherit" %s'
            ];
            break;
        case 'chmod_bad':
            $commands = ['chmod -R a+w %s'];
            break;
    }

    $releasePath = env()->getReleasePath();
    foreach ($dirs as $dir) {
        // Create shared dir if does not exist
        RunHelper::exec("mkdir -p $releasePath/$dir");
        foreach ($commands as $command) {
            RunHelper::exec(sprintf($command, $dir));
        }
    }
})->desc('Make writable dirs');

/**
 * Create symlinks for shared directories and files
 */
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

/**
 * Vendors
 */
task('local:vendors', function (InputInterface $input) {
    if (!$input->getOption('skip-vendors')) {
        $releasePath = env()->getReleasePath();

        cd($releasePath);
        $prod = get('env', 'dev');

        if (programExist('composer')) {
            $composer = 'composer';
        } else {
            RunHelper::exec("curl -s http://getcomposer.org/installer | php");
            $composer = 'php composer.phar';
        }

        RunHelper::exec("SYMFONY_ENV=$prod $composer install --quiet");
    }
})->option('skip-vendors', null, 'Skip local:vendors task', false)
    ->desc('Installing vendors');

/**
 * Warm up cache
 */
task('local:cache:warmup', function () {
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
 * Dump all assets to the filesystem
 */
task('local:assetic:install', function () {
    $releasePath = env()->getReleasePath();
    $prod = get('env', 'dev');

    RunHelper::exec("$releasePath/app/console assets:install --env=$prod --symlink");

})->desc('Dumping assets');

/**
 * Migrate database
 */
task('local:database:migrate', function () {
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

//Helper task
task('local:start', function () {
})->desc('Helper task start');

task('local:end', function () {
})->desc('Helper task end');

/**
 * Main task
 */
task('local', [
    'local:set_env',
    'local:start',
    'local:prepare',
    'local:update_code',
    'local:shared',
    'local:writable_dirs',
    'local:vendors',
    'local:cache:warmup',
    'local:assetic:install',
    'local:database:migrate',
    'local:end'
])->option('branch', 'b', 'Project branch', false)
    ->option('skip-vendors', null, 'Skip local:vendors task', false)
    ->desc('Update your local project');