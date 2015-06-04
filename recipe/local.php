<?php
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasArgument('stage')) {
    argument('stage', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Run tasks only on this server or group of servers.');
}
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasArgument('branch')) {
    argument('branch', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Release branch', 'master');
}
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasOption('env')) {
    option('env', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Symfony env');
}

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
task('local:prepare', function () {
    set('locally', true);

    // Check if shell is POSIX-compliant
    try {
        cd(''); // To run command as raw.
        \TheRat\SymDep\runCommand('echo $0', get('locally'));
    } catch (\RuntimeException $e) {
        $formatter = \Deployer\Deployer::get()->getHelper('formatter');

        $errorMessage = [
            "Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.",
            "Usually, you can change your shell to bash by running: chsh -s /bin/bash",
        ];
        write($formatter->formatBlock($errorMessage, 'error', true));

        throw $e;
    }

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

    //Doctrine cache clear
    set('doctrine_cache_clear', true);

    // Environment vars
    $env = input()->getOption('env') ? input()->getOption('env') : 'dev';
    env('env_vars', "SYMFONY_ENV=$env");
    env('env', $env);
    env('branch', false);

    // Adding support for the Symfony3 directory structure
    set('bin_dir', 'app');
    set('var_dir', 'app');

    env('release_path', env('deploy_path'));
    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');
})->desc('Preparing server for deploy');

/**
 * Update project code
 */
task('local:update_code', function () {
    $branch = env('branch');
    if (false === $branch) {
        $branch = \TheRat\SymDep\runCommand('cd {{release_path}} && git rev-parse --abbrev-ref HEAD', get('locally'))
            ->toString();
    }
    $res = \TheRat\SymDep\runCommand("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)", get('locally'));
    if ($res) {
        \TheRat\SymDep\runCommand("git pull origin $branch 2>&1", get('locally'));
    } else {
        writeln("<comment>Found local git branch. Pulling skipped.</comment>");
    }
})->desc('Updating code');

/**
 * Create cache dir
 */
task('local:create_cache_dir', function () {
    // Set cache dir
    env('cache_dir', trim(get('var_dir'), '/') . '/cache');

    // Remove cache dir if it exist
    \TheRat\SymDep\runCommand('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi', get('locally'));;

    // Create cache dir
    \TheRat\SymDep\runCommand('mkdir -p {{cache_dir}}', get('locally'));;

    // Set rights
    \TheRat\SymDep\runCommand("chmod -R g+w {{cache_dir}}", get('locally'));;
})->desc('Create cache dir');

/**
 * Create symlinks for shared directories and files.
 */
task('local:shared', function () {
    $sharedPath = "{{deploy_path}}/shared";

    foreach (get('shared_dirs') as $dir) {
        // Remove from source
        \TheRat\SymDep\runCommand("if [ -d $(echo {{release_path}}/$dir) ]; then rm -rf {{release_path}}/$dir; fi", get('locally'));;

        // Create shared dir if it does not exist
        \TheRat\SymDep\runCommand("mkdir -p $sharedPath/$dir", get('locally'));;

        // Create path to shared dir in release dir if it does not exist
        // (symlink will not create the path and will fail otherwise)
        \TheRat\SymDep\runCommand("mkdir -p `dirname {{release_path}}/$dir`", get('locally'));;

        // Symlink shared dir to release dir
        \TheRat\SymDep\runCommand("ln -nfs $sharedPath/$dir {{release_path}}/$dir", get('locally'));;
    }

    foreach (get('shared_files') as $file) {
        // Remove from source
        \TheRat\SymDep\runCommand("if [ -d $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi", get('locally'));;

        // Create dir of shared file
        runLocally("mkdir -p $sharedPath/" . dirname($file));

        // Touch shared
        \TheRat\SymDep\runCommand("touch $sharedPath/$file", get('locally'));;

        // Symlink shared dir to release dir
        \TheRat\SymDep\runCommand("ln -nfs $sharedPath/$file {{release_path}}/$file", get('locally'));;
    }
})->desc('Creating symlinks for shared files');

/**
 * Make writable dirs.
 */
task('local:writable', function () {
    $dirs = join(' ', get('writable_dirs'));

    if (!empty($dirs)) {
        \TheRat\SymDep\runCommand("chmod 777 $dirs", get('locally'));;
    }

})->desc('Make writable dirs');

/**
 * Normalize asset timestamps
 */
task('local:assets', function () {
    $assets = array_map(function ($asset) {
        return "{{release_path}}/$asset";
    }, get('assets'));

    $time = date('Ymdhi.s');

    foreach ($assets as $dir) {
        if (\TheRat\SymDep\runCommand("if [ -d $(echo $dir) ]; then echo 1; fi", get('locally'))->toBool()) {
            \TheRat\SymDep\runCommand("find $dir -exec touch -t $time {} ';' &> /dev/null || true", get('locally'));
        }
    }
})->desc('Normalize asset timestamps');

/**
 * Installing vendors tasks.
 */
task('local:vendors', function () {
    if (\TheRat\SymDep\runCommand("if hash composer 2>/dev/null; then echo 'true'; fi", get('locally'))->toBool()) {
        $composer = 'composer';
    } else {
        \TheRat\SymDep\runCommand("cd {{release_path}} && curl -sS https://getcomposer.org/installer | php", get('locally'));;
        $composer = 'php composer.phar';
    }

    \TheRat\SymDep\runCommand("cd {{release_path}} && {{env_vars}} $composer install --verbose --prefer-dist --optimize-autoloader --no-progress --no-interaction", get('locally'));;

})->desc('Installing vendors');

/**
 * Dump all assets to the filesystem
 */
task('local:assetic:dump', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} assetic:dump --env={{env}} --quiet', get('locally'));;
    \TheRat\SymDep\runCommand('{{symfony_console}} assets:install --symlink --env={{env}} --quiet', get('locally'));;

})->desc('Dump assets');

/**
 * Warm up cache
 */
task('local:cache:warmup', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} cache:warmup  --env={{env}}', get('locally'));;

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('local:database:migrate', function () {
    if (get('auto_migrate')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-interaction', get('locally'));;
    }
})->desc('Migrate database');

/**
 * Doctrine cache clear database
 */
task('local:database:cache-clear', function () {
    if (get('doctrine_cache_clear')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}}', get('locally'));;
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-query --env={{env}}', get('locally'));;
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-result --env={{env}}', get('locally'));;
    }
})->desc('Doctrine cache clear');

/**
 * Main task
 */
task('project-update', [
    'local:prepare',
    'local:update_code',
    'local:create_cache_dir',
    'local:shared',
    'local:writable',
    'local:assets',
    'local:vendors',
    'local:assetic:dump',
    'local:cache:warmup',
    'local:database:migrate',
    'local:database:cache-clear',
])->desc('Local project update');

after('project-update', 'success');
