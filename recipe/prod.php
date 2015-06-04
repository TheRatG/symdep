<?php

/**
 * Return list of releases on server.
 */
env('releases_list', function () {
    $list = run('ls {{deploy_path}}/releases')->toArray();

    rsort($list);

    return $list;
});

/**
 * Return current release path.
 */
env('current', function () {
    return run("readlink {{deploy_path}}/current")->toString();
});

/**
 * Show current release number.
 */
task('current', function () {
    writeln('Current release: ' . basename(env('current')));
})->desc('Show current release.');

/**
 * Rollback to previous release.
 */
task('rollback', function () {
    $releases = env('releases_list');

    if (isset($releases[1])) {
        $releaseDir = "{{deploy_path}}/releases/{$releases[1]}";

        // Symlink to old release.
        \TheRat\SymDep\runCommand("cd {{deploy_path}} && ln -nfs $releaseDir current", get('locally'));

        // Remove release
        \TheRat\SymDep\runCommand("rm -rf {{deploy_path}}/releases/{$releases[0]}", get('locally'));

        if (isVerbose()) {
            writeln("Rollback to `{$releases[1]}` release was successful.");
        }
    } else {
        writeln("<comment>No more releases you can revert to.</comment>");
    }
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
task('deploy-on-prod:prepare', function () {
    set('keep_releases', 3);

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

    // Environment vars
    env('env_vars', 'SYMFONY_ENV=prod');
    env('env', 'prod');

    // Adding support for the Symfony3 directory structure
    set('bin_dir', 'app');
    set('var_dir', 'app');

    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');

    if (!get('locally')) {
        \Deployer\Task\Context::get()->getServer()->connect();
    }

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

    \TheRat\SymDep\runCommand('if [ ! -d {{deploy_path}} ]; then echo ""; fi', get('locally'));

    // Create releases dir.
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ ! -d releases ]; then mkdir releases; fi", get('locally'));

    // Create shared dir.
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ ! -d shared ]; then mkdir shared; fi", get('locally'));
})->desc('Preparing server for deploy');

task('deploy-on-prod:update_code', function () {
    $releasePath = env('release_path');
    $repository = get('repository');
    $branch = env('branch');

    if (\TheRat\SymDep\dirExists($releasePath)) {
        \TheRat\SymDep\runCommand(
            "cd $releasePath && git pull origin $branch --quiet",
            get('locally')
        );
    } else {
        \TheRat\SymDep\runCommand("mkdir -p $releasePath", get('locally'));
        \TheRat\SymDep\runCommand(
            "cd $releasePath && git clone -b $branch --depth 1 --recursive -q $repository $releasePath",
            get('locally')
        );
    }
})->desc('Updating code');

/**
 * Create cache dir
 */
task('deploy:create_cache_dir', function () {
    // Set cache dir
    env('cache_dir', '{{release_path}}/' . trim(get('var_dir'), '/') . '/cache');

    // Remove cache dir if it exist
    \TheRat\SymDep\runCommand('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi', get('locally'));

    // Create cache dir
    \TheRat\SymDep\runCommand('mkdir -p {{cache_dir}}', get('locally'));

    // Set rights
    run("chmod -R g+w {{cache_dir}}");
})->desc('Create cache dir');


/**
 * Normalize asset timestamps
 */
task('deploy-on-prod:assets', function () {
    $assets = array_map(function ($asset) {
        return "{{release_path}}/$asset";
    }, get('assets'));

    $time = date('Ymdhi.s');

    foreach ($assets as $dir) {
        if (\TheRat\SymDep\dirExists($dir)) {
            run("find $dir -exec touch -t $time {} ';' &> /dev/null || true");
        }
    }
})->desc('Normalize asset timestamps');


/**
 * Dump all assets to the filesystem
 */
task('deploy-on-prod:assetic:dump', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} assetic:dump --env={{env}} --no-debug', get('locally'));

})->desc('Dump assets');


/**
 * Warm up cache
 */
task('deploy-on-prod:cache:warmup', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} cache:warmup  --env={{env}} --no-debug', get('locally'));
    \TheRat\SymDep\runCommand('{{symfony_console}} assets:install --env={{env}} --no-debug', get('locally'));

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('deploy-on-prod:database:migrate', function () {
    if (get('auto_migrate')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-debug --no-interaction', get('locally'));
    }
})->desc('Migrate database');

/**
 * Create symlink to last release.
 */
task('deploy-on-prod:symlink', function () {
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && ln -sfn {{release_path}} current", get('locally')); // Atomic override symlink.
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && rm release", get('locally')); // Remove release link.
})->desc('Creating symlink to release');

/**
 * Doctrine cache clear database
 */
task('deploy-on-prod:database:cache-clear', function () {
    if (get('doctrine_cache_clear')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}} --no-debug', get('locally'));
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-query --env={{env}} --no-debug', get('locally'));
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-result --env={{env}} --no-debug', get('locally'));
    }
})->desc('Doctrine cache clear');

/**
 * Cleanup old releases.
 */
task('deploy-on-prod:cleanup', function () {
    $releases = env('releases_list');

    $keep = get('keep_releases');

    while ($keep > 0) {
        array_shift($releases);
        --$keep;
    }

    foreach ($releases as $release) {
        \TheRat\SymDep\runCommand("rm -rf {{deploy_path}}/releases/$release", get('locally'));
    }

    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ -e release ]; then rm release; fi", get('locally'));
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ -h release ]; then rm release; fi", get('locally'));

})->desc('Cleaning up old releases');

/**
 * Main task
 */
task('deploy-on-prod', [
    'deploy-on-prod:prepare',
    'deploy-on-prod:update_code',
    'symdep:create_cache_dir',
    'symdep:shared',
    'symdep:writable',
    'deploy-on-prod:assets',
    'symdep:vendors',
    'deploy-on-prod:assetic:dump',
    'deploy-on-prod:cache:warmup',
    'deploy-on-prod:database:migrate',
    'deploy-on-prod:symlink',
    'deploy-on-prod:database:cache-clear',
    'deploy-on-prod:cleanup',
])->desc('Deploy your project on "prod"');

after('deploy-on-prod', 'success');
