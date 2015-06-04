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
task('deploy-on-test:prepare', function () {

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
    set('auto_migrate', true);

    //Doctrine cache clear
    set('doctrine_cache_clear', true);

    set('writable_use_sudo', false);

    // Adding support for the Symfony3 directory structure
    set('bin_dir', 'app');
    set('var_dir', 'app');

    $branch = input()->getArgument('branch');
    env('branch', $branch);
    env('release_path', env()->parse('{{deploy_path}}') . "/releases/$branch");
    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');

    // Environment vars
    env('env_real', 'test');
    $env = 'test';
    if ('master' == $branch) {
        $env = 'prod';
    }
    env('env_vars', "SYMFONY_ENV=$env");
    env('env', $env);

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

    \TheRat\SymDep\runCommand('if [ ! -d {{deploy_path}} ]; then echo ""; fi');
})->desc('Preparing server for deploy');

task('deploy-on-test:update_code', function () {
    $releasePath = env('release_path');
    $repository = get('repository');
    $branch = env('branch');

    if (\TheRat\SymDep\dirExists($releasePath)) {
        \TheRat\SymDep\runCommand("cd $releasePath && git pull origin $branch --quiet");
    } else {
        \TheRat\SymDep\runCommand("mkdir -p $releasePath");
        \TheRat\SymDep\runCommand(
            "cd $releasePath && git clone -b $branch --depth 1 --recursive -q $repository $releasePath",
            get('locally')
        );
    }
})->desc('Updating code');

/**
 * Normalize asset timestamps
 */
task('deploy-on-test:assets', function () {
    $assets = array_map(function ($asset) {
        return "{{release_path}}/$asset";
    }, get('assets'));

    $time = date('Ymdhi.s');

    foreach ($assets as $dir) {
        if (\TheRat\SymDep\dirExists($dir)) {
            \TheRat\SymDep\runCommand("find $dir -exec touch -t $time {} ';' &> /dev/null || true");
        }
    }
})->desc('Normalize asset timestamps');


/**
 * Dump all assets to the filesystem
 */
task('deploy-on-test:assetic:dump', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} assetic:dump --env={{env}} --no-debug');

})->desc('Dump assets');


/**
 * Warm up cache
 */
task('deploy-on-test:cache:warmup', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} cache:warmup  --env={{env}} --no-debug');
    \TheRat\SymDep\runCommand('{{symfony_console}} assets:install --env={{env}} --no-debug');

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('deploy-on-test:database:migrate', function () {
    if (get('auto_migrate')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-debug --no-interaction');
    }
})->desc('Migrate database');

/**
 * Doctrine cache clear database
 */
task('deploy-on-test:database:cache-clear', function () {
    if (get('doctrine_cache_clear')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}} --no-debug');
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-query --env={{env}} --no-debug');
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-result --env={{env}} --no-debug');
    }
})->desc('Doctrine cache clear');

/**
 * Main task
 */
task('deploy-on-test', [
    'deploy-on-test:prepare',
    'deploy-on-test:update_code',
    'symdep:create_cache_dir',
    'symdep:shared',
    'symdep:writable',
    'deploy-on-test:assets',
    'symdep:vendors',
    'deploy-on-test:assetic:dump',
    'deploy-on-test:cache:warmup',
    'deploy-on-test:database:migrate',
    'deploy-on-test:database:cache-clear',
])->desc('Deploy your project on "test"');

after('deploy-on-test', 'success');
