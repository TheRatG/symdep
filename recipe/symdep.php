<?php
/**
 * Default arguments and options.
 */
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasArgument('stage')) {
    argument('stage', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Run tasks only on this server or group of servers.');
}
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasArgument('branch')) {
    argument('branch', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Release branch', 'master');
}
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasOption('locally')) {
    option('locally', 'l', \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Run command locally');
}

//number of releases
set('keep_releases', 5);

/**
 * Create symlinks for shared directories and files.
 */
task('symdep:shared', function () {
    $sharedPath = "{{deploy_path}}/shared";

    foreach (get('shared_dirs') as $dir) {
        // Remove from source
        \TheRat\SymDep\runCommand("if [ -d $(echo {{release_path}}/$dir) ]; then rm -rf {{release_path}}/$dir; fi");

        // Create shared dir if it does not exist
        \TheRat\SymDep\runCommand("mkdir -p $sharedPath/$dir");

        // Create path to shared dir in release dir if it does not exist
        // (symlink will not create the path and will fail otherwise)
        \TheRat\SymDep\runCommand("mkdir -p `dirname {{release_path}}/$dir`");

        // Symlink shared dir to release dir
        \TheRat\SymDep\runCommand("ln -nfs $sharedPath/$dir {{release_path}}/$dir");
    }

    foreach (get('shared_files') as $file) {
        // Remove from source
        \TheRat\SymDep\runCommand("if [ -f $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi");

        // Create dir of shared file
        \TheRat\SymDep\runCommand("mkdir -p $sharedPath/" . dirname($file));

        // Touch shared
        \TheRat\SymDep\runCommand("touch $sharedPath/$file");

        // Symlink shared dir to release dir
        \TheRat\SymDep\runCommand("ln -nfs $sharedPath/$file {{release_path}}/$file");
    }
})->desc('Creating symlinks for shared files');

/**
 * Create cache dir
 */
task('symdep:create_cache_dir', function () {
    // Set cache dir
    env('cache_dir', '{{release_path}}/' . trim(get('var_dir'), '/') . '/cache');

    // Remove cache dir if it exist
    \TheRat\SymDep\runCommand('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');

    // Create cache dir
    \TheRat\SymDep\runCommand('mkdir -p {{cache_dir}}');

    // Set rights
    \TheRat\SymDep\runCommand("chmod -R g+w {{cache_dir}}");
})->desc('Create cache dir');

/**
 * Make writable dirs.
 */
task('symdep:writable', function () {
    $dirs = join(' ', get('writable_dirs'));

    if (!empty($dirs)) {
        \TheRat\SymDep\runCommand("cd {{release_path}} && chmod 777 $dirs");
    }

})->desc('Make writable dirs');

/**
 * Installing vendors tasks.
 */
task('symdep:vendors', function () {
    if (\TheRat\SymDep\runCommand("if hash composer 2>/dev/null; then echo 'true'; fi")->toBool()) {
        $composer = 'composer';
    } else {
        \TheRat\SymDep\runCommand("cd {{release_path}} && curl -sS https://getcomposer.org/installer | php");
        $composer = 'php composer.phar';
    }

    $require = env('env') !== 'dev' ? '--no-dev' : '--dev';
    $options = "--prefer-dist --optimize-autoloader --no-progress --no-interaction --quiet $require";

    \TheRat\SymDep\runCommand(
        "cd {{release_path}} && {{env_vars}} $composer install $options",
        get('locally')
    );

    sleep(5);

})->desc('Installing vendors');

/**
 * Rollback to previous release.
 */
task('rollback', function () {
})->desc('Rollback to previous release');

require_once 'local.php';
require_once 'test.php';
require_once 'prod.php';
