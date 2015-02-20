<?php
namespace SymDep\Recipe;

use Symfony\Component\Console\Input\InputInterface;

/**
 * All commands runLocally
 * @param $command
 * @param bool $raw
 * @return string
 */
function run($command, $raw = false)
{
    if (!$raw) {
        $workingPath = env()->getWorkingPath();
        $command = "cd {$workingPath} && $command";
    }

    if (output()->isVerbose()) {
        output()->writeln($command);
    }
    return runLocally($command);
}

task('local:start', function () {
})->desc('Helper task start');

task('local:set_env', function () {
    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs']);

    //Doctrine
    set('doctrine_auto_migrate', false);
    set('doctrine_clear_cache', false);

    set('env', 'dev');

    //use in local:writable_dirs
    set('permission_method', 'chmod_bad');
    env()->setReleasePath(config()->getPath());

})->desc('Preparing env variables');

/**
 * Preparing server for deployment.
 */
task('local:prepare', function () {
    $basePath = config()->getPath();

    // Check if base path exist.
    run("if [ ! -d $(echo $basePath) ]; then mkdir $basePath; fi", true);

    // Create shared dir.
    run("if [ ! -d \"shared\" ]; then mkdir shared; fi");
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
    $isGit = is_dir($basePath . '.git');
    if ($isGit) {
        $branch = $input->getOption('branch');
        $res = run("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)");
    } else {
        if (output()->isVerbose()) {
            output()->writeln("<comment>Update code skipped.</comment>");
        }
    }
})->desc('Updating code');

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

    switch ($permissionMethod) {
        case 'acl':
            $run = run("if which setfacl; then echo \"ok\"; fi");
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
        run("mkdir -p $releasePath/$dir");
        foreach ($commands as $command) {
            run(sprintf($command, $dir));
        }
    }
})->desc('Make writable dirs');

/**
 * Vendors
 */
task('local:vendors', function () {
    $releasePath = env()->getReleasePath();

    cd($releasePath);
    $prod = get('env', 'dev');
    $isComposer = run("if [ -e $releasePath/composer.phar ]; then echo 'true'; fi");

    if ('true' !== $isComposer) {
        run("curl -s http://getcomposer.org/installer | php");
    }

    run("SYMFONY_ENV=$prod php composer.phar install");

})->desc('Installing vendors');

/**
 * Warm up cache
 */
task('local:cache', function () {
    $releasePath = env()->getReleasePath();
    $cacheDir = env()->get('cache_dir', "$releasePath/app/cache");

    $prod = get('env', 'dev');

    run("php $releasePath/app/console cache:clear --no-warmup --env=$prod");


    if(get('doctrine_clear_cache', false))
    {
        run("php $releasePath/app/console doctrine:cache:clear-metadata --env=$prod");
        run("php $releasePath/app/console doctrine:cache:clear-query --env=$prod");
        run("php $releasePath/app/console doctrine:cache:clear-result --env=$prod");
    }

    run("php $releasePath/app/console cache:warmup  --env=$prod");

    run("chmod -R g+w $cacheDir");
})->desc('Clear and warming up cache');

/**
 * Dump all assets to the filesystem
 */
task('local:assetic:install', function () {
    $releasePath = env()->getReleasePath();
    $prod = get('env', 'dev');

    run("php $releasePath/app/console assets:install --env=$prod --symlink");

})->desc('Dumping assets');

/**
 * Migrate database
 */
task('local:database:migrate', function () {
    $releasePath = env()->getReleasePath();
    $prod = get('env', 'dev');
    $serverName = config()->getName();

    $run = get('doctrine_auto_migrate', false);

    if (output()->isVerbose()) {
        $run = askConfirmation("Run migrations on $serverName server?", $run);
    }

    if ($run) {
        run("php $releasePath/app/console doctrine:migrations:migrate --env=$prod --no-interaction");
    }

})->desc('Migrating database');

task('local:end', function () {
})->desc('Helper task end');

/**
 * Main task
 */
task('local', [
    'local:start',
    'local:set_env',
    'local:prepare',
    'local:update_code',
    'local:writable_dirs',
    'local:vendors',
    'local:cache',
    'local:assetic:install',
    'local:database:migrate',
    'local:end'
])->option('branch', 'b', 'Project branch', 'master')
    ->desc('Update your local project');