<?php
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

    //run command remote or locally
    set('locally', input()->getOption('locally'));

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
    $env = 'dev';
    env('env_vars', "SYMFONY_ENV=$env");
    env('env', $env);
    env('branch', false);

    // Adding support for the Symfony3 directory structure
    set('bin_dir', 'app');
    set('var_dir', 'app');

    env('release_path', env('deploy_path'));
    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');

    // Check if shell is POSIX-compliant
    try {
        cd(''); // To run command as raw.
        \TheRat\SymDep\runCommand('echo $0');
    } catch (\RuntimeException $e) {
        $formatter = \Deployer\Deployer::get()->getHelper('formatter');

        $errorMessage = [
            "Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.",
            "Usually, you can change your shell to bash by running: chsh -s /bin/bash",
        ];
        write($formatter->formatBlock($errorMessage, 'error', true));

        throw $e;
    }

})->desc('Preparing server for deploy');

/**
 * Update project code
 */
task('local:update_code', function () {
    $branch = env('branch');
    if (false === $branch) {
        $branch = \TheRat\SymDep\runCommand('cd {{release_path}} && git rev-parse --abbrev-ref HEAD')
            ->toString();
    }
    $res = \TheRat\SymDep\runCommand("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)");
    if ($res) {
        \TheRat\SymDep\runCommand("git pull origin $branch 2>&1");
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
    \TheRat\SymDep\runCommand('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');

    // Create cache dir
    \TheRat\SymDep\runCommand('mkdir -p {{cache_dir}}');

    // Set rights
    \TheRat\SymDep\runCommand("chmod -R g+w {{cache_dir}}");
})->desc('Create cache dir');

/**
 * Normalize asset timestamps
 */
task('local:assets', function () {
    $assets = array_map(function ($asset) {
        return "{{release_path}}/$asset";
    }, get('assets'));

    $time = date('Ymdhi.s');

    foreach ($assets as $dir) {
        if (\TheRat\SymDep\runCommand("if [ -d $(echo $dir) ]; then echo 1; fi")->toBool()) {
            \TheRat\SymDep\runCommand("find $dir -exec touch -t $time {} ';' &> /dev/null || true");
        }
    }
})->desc('Normalize asset timestamps');

/**
 * Dump all assets to the filesystem
 */
task('local:assetic:dump', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} assetic:dump --env={{env}} --quiet');
    \TheRat\SymDep\runCommand('{{symfony_console}} assets:install --symlink --env={{env}} --quiet');

})->desc('Dump assets');

/**
 * Warm up cache
 */
task('local:cache:warmup', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} cache:warmup  --env={{env}}');

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('local:database:migrate', function () {
    if (get('auto_migrate')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-interaction');
    }
})->desc('Migrate database');

/**
 * Doctrine cache clear database
 */
task('local:database:cache-clear', function () {
    if (get('doctrine_cache_clear')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}}');
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-query --env={{env}}');
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-result --env={{env}}');
    }
})->desc('Doctrine cache clear');

/**
 * Main task
 */
task('project-update', [
    'local:prepare',
    'local:update_code',
    'local:create_cache_dir',
    'symdep:shared',
    'symdep:writable',
    'local:assets',
    'symdep:vendors',
    'local:assetic:dump',
    'local:cache:warmup',
    'local:database:migrate',
    'local:database:cache-clear',
])->desc('Local project update');

after('project-update', 'success');
