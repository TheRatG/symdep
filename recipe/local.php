<?php
/**
 * Symfony Configuration
 */

// Symfony shared dirs
set('shared_dirs', ['app/cache', 'app/logs', 'web/uploads']);

// Symfony shared files
set('shared_files', ['app/config/parameters.yml']);

// Symfony writable dirs
set('writable_dirs', ['app/cache', 'app/logs', 'web/uploads']);

// Assets
set('assets', ['web/css', 'web/images', 'web/js']);

// Auto migrate
set('auto_migrate', false);

// Environment vars
env('env_vars', 'SYMFONY_ENV=dev');
env('env', 'dev');
env('branch', false);

// Adding support for the Symfony3 directory structure
set('bin_dir', 'app');
set('var_dir', 'app');

/**
 * Default arguments and options.
 */
argument('stage', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Run tasks only on this server or group of servers.');

/**
 * Rollback to previous release.
 */
task('rollback', function () {
})->desc('Rollback to previous release');

/**
 * Success message
 */
task('success', function () {
    writeln("<info>Successfully deployed!</info>");
})
    ->once()
    ->setPrivate();

/**
 * Preparing server for deployment.
 */
task('project-update:prepare', function () {
    // Check if shell is POSIX-compliant
    try {
        cd(''); // To run command as raw.
        runLocally('echo $0');
    } catch (\RuntimeException $e) {
        $formatter = \Deployer\Deployer::get()->getHelper('formatter');

        $errorMessage = [
            "Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.",
            "Usually, you can change your shell to bash by running: chsh -s /bin/bash",
        ];
        write($formatter->formatBlock($errorMessage, 'error', true));

        throw $e;
    }

    runLocally('if [ ! -d {{deploy_path}} ]; then echo ""; fi');

    env('release_path', env('deploy_path'));
    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');
})->desc('Preparing server for deploy');

/**
 * Update project code
 */
task('project-update:update_code', function () {
    $branch = env('branch');
    if (false == $branch) {
        $branch = runLocally('cd {{deploy_path}} && git rev-parse --abbrev-ref HEAD')->toString();
    }
    $res = runLocally("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)");
    if ($res) {
        runLocally("git pull origin $branch 2>&1");
    } else {
        writeln("<comment>Found local git branch. Pulling skipped.</comment>");
    }
})->desc('Updating code');

/**
 * Create cache dir
 */
task('project-update:create_cache_dir', function () {
    // Set cache dir
    env('cache_dir', trim(get('var_dir'), '/') . '/cache');

    // Remove cache dir if it exist
    runLocally('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');

    // Create cache dir
    runLocally('mkdir -p {{cache_dir}}');

    // Set rights
    runLocally("chmod -R g+w {{cache_dir}}");
})->desc('Create cache dir');

/**
 * Create symlinks for shared directories and files.
 */
task('project-update:shared', function () {
    $sharedPath = "{{deploy_path}}/shared";

    foreach (get('shared_dirs') as $dir) {
        // Remove from source
        runLocally("if [ -d $(echo {{release_path}}/$dir) ]; then rm -rf {{release_path}}/$dir; fi");

        // Create shared dir if it does not exist
        runLocally("mkdir -p $sharedPath/$dir");

        // Create path to shared dir in release dir if it does not exist
        // (symlink will not create the path and will fail otherwise)
        runLocally("mkdir -p `dirname {{release_path}}/$dir`");

        // Symlink shared dir to release dir
        runLocally("ln -nfs $sharedPath/$dir {{release_path}}/$dir");
    }

    foreach (get('shared_files') as $file) {
        // Remove from source
        runLocally("if [ -d $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi");

        // Create dir of shared file
        runLocally("mkdir -p $sharedPath/" . dirname($file));

        // Touch shared
        runLocally("touch $sharedPath/$file");

        // Symlink shared dir to release dir
        runLocally("ln -nfs $sharedPath/$file {{release_path}}/$file");
    }
})->desc('Creating symlinks for shared files');

/**
 * Make writable dirs.
 */
task('project-update:writable', function () {
    $dirs = join(' ', get('writable_dirs'));

    if (!empty($dirs)) {
        runLocally("chmod 777 $dirs");
    }

})->desc('Make writable dirs');

/**
 * Normalize asset timestamps
 */
task('project-update:assets', function () {
    $assets = array_map(function ($asset) {
        return "{{release_path}}/$asset";
    }, get('assets'));

    $time = date('Ymdhi.s');

    foreach ($assets as $dir) {
        if (runLocally("if [ -d $(echo $dir) ]; then echo 1; fi")->toBool()) {
            runLocally("find $dir -exec touch -t $time {} ';' &> /dev/null || true");
        }
    }
})->desc('Normalize asset timestamps');

/**
 * Installing vendors tasks.
 */
task('project-update:vendors', function () {
    if (runLocally("if hash composer 2>/dev/null; then echo 'true'; fi")->toBool()) {
        $composer = 'composer';
    } else {
        runLocally("cd {{release_path}} && curl -sS https://getcomposer.org/installer | php");
        $composer = 'php composer.phar';
    }

    runLocally("cd {{release_path}} && {{env_vars}} $composer install --verbose --prefer-dist --optimize-autoloader --no-progress --no-interaction");

})->desc('Installing vendors');

/**
 * Dump all assets to the filesystem
 */
task('project-update:assetic:dump', function () {

    runLocally('{{symfony_console}} assetic:dump --env={{env}} --no-debug');
    runLocally('{{symfony_console}} assets:install --symlink --env={{env}} --no-debug');

})->desc('Dump assets');

/**
 * Warm up cache
 */
task('project-update:cache:warmup', function () {

    runLocally('{{symfony_console}} cache:warmup  --env={{env}} --no-debug');

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('project-update:database:migrate', function () {
    if (get('auto_migrate')) {
        runLocally('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-debug --no-interaction');
    }
})->desc('Migrate database');

/**
 * Main task
 */
task('project-update', [
    'project-update:prepare',
    'project-update:update_code',
    'project-update:create_cache_dir',
    'project-update:shared',
    'project-update:writable',
    'project-update:assets',
    'project-update:vendors',
    'project-update:assetic:dump',
    'project-update:cache:warmup',
    'project-update:database:migrate',
])->desc('Deploy your project');

after('project-update', 'success');
