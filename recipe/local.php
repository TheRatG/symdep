<?php
namespace TheRat\SymDep\Recipe;

use Symfony\Component\Console\Input\InputInterface;

$basePath = realpath(__DIR__ . '/../../../');
require_once $basePath . '/therat/symdep/functions.php';

if (!function_exists('commandExist')) {
    /**
     * Check if command exist in bash.
     *
     * @param string $command
     * @return bool
     */
    function commandExist($command)
    {
        $res = run("if hash $command 2>/dev/null; then echo 'true'; fi");
        if ('true' === trim($res)) {
            return true;
        } else {
            return false;
        }
    }
}

function getCurrentBranch(InputInterface $input = null)
{
    $branch = get('branch', false);
    if (!$branch) {
        $branch = $input->getOption('branch');
        if (!$branch) {
            $branch = trim(runLocally('git rev-parse --abbrev-ref HEAD'));
        }
        set('branch', $branch);
    }
    return $branch;
}

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

task('local:set_env', function () {
    set('env', 'dev');

    // Symfony shared dirs
    set('shared_dirs', ['app/logs', 'app/sessions', 'web/uploads']);

    // Symfony shared files
    set('shared_files', []);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs']);

    //Doctrine
    set('doctrine_auto_migrate', true);
    set('doctrine_clear_cache', false);

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
    $isGit = is_dir($basePath . '/.git');
    if ($isGit) {
        $branch = getCurrentBranch($input);
        $res = run("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)");
        if ($res) {
            run("git pull origin $branch --quiet");
        } else {
            if (output()->isVerbose()) {
                output()->writeln("<comment>Found local git branch. Pulling skipped.</comment>");
            }
        }
    } else {
        if (output()->isVerbose()) {
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
        run("if [ -d $(echo $releasePath/$dir) ]; then rm -rf $releasePath/$dir; fi");

        // Create shared dir if does not exist
        run("mkdir -p $sharedPath/$dir");

        // Symlink shared dir to release dir
        run("ln -nfs $sharedPath/$dir $releasePath/$dir");
    }

    // User specified shared files
    $sharedFiles = (array)get('shared_files', []);

    foreach ($sharedFiles as $file) {
        // Create dir of shared file
        run("mkdir -p $sharedPath/" . dirname($file));

        // Touch shared file
        run("touch $sharedPath/$file");

        // Symlink shared file to release file
        run("ln -nfs $sharedPath/$file $releasePath/$file");
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

        if (commandExist('composer')) {
            $composer = 'composer';
        } else {
            run("curl -s http://getcomposer.org/installer | php");
            $composer = 'php composer.phar';
        }

        run("SYMFONY_ENV=$prod $composer install --verbose");
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

    run("php $releasePath/app/console cache:clear --no-warmup --env=$prod --no-debug");


    if (get('doctrine_clear_cache', false)) {
        run("$releasePath/app/console doctrine:cache:clear-metadata --env=$prod --no-debug");
        run("$releasePath/app/console doctrine:cache:clear-query --env=$prod --no-debug");
        run("$releasePath/app/console doctrine:cache:clear-result --env=$prod --no-debug");
    }

    run("$releasePath/app/console cache:warmup  --env=$prod --no-debug");

    run("chmod -R g+w $cacheDir");
})->desc('Clear and warming up cache');

/**
 * Dump all assets to the filesystem
 */
task('local:assetic:install', function () {
    $releasePath = env()->getReleasePath();
    $prod = get('env', 'dev');

    run("$releasePath/app/console assets:install --env=$prod --symlink --no-debug");

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
        run("$releasePath/app/console doctrine:migrations:migrate --env=$prod --no-interaction --no-debug");
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