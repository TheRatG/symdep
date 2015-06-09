<?php

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
        \TheRat\SymDep\runCommand("cd {{deploy_path}} && ln -nfs $releaseDir current");

        // Remove release
        \TheRat\SymDep\runCommand("rm -rf {{deploy_path}}/releases/{$releases[0]}");

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

    // Environment vars
    env('env_vars', 'SYMFONY_ENV=prod');
    env('env', 'prod');

    // Adding support for the Symfony3 directory structure
    set('bin_dir', 'app');
    set('var_dir', 'app');

    $branch = input()->getArgument('branch');
    env('branch', $branch);

    $release = date('YmdHis');
    /**
     * Return release path.
     */
    env('release_path', "{{deploy_path}}/releases/$release");

    /**
     * Return current release path.
     */
    env('current', function () {
        return \TheRat\SymDep\runCommand("readlink {{deploy_path}}/current")->toString();
    });

    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');

    if (!get('locally')) {
        \Deployer\Task\Context::get()->getServer()->connect();
    }

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

    // Create releases dir.
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ ! -d releases ]; then mkdir releases; fi");

    // Create shared dir.
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ ! -d shared ]; then mkdir shared; fi");
})->desc('Preparing server for deploy');

/**
 * Release
 */
task('deploy-on-prod:release', function () {
    $releasePath = env('release_path');

    $i = 0;
    while (is_dir(env()->parse($releasePath)) && $i < 42) {
        $releasePath .= '.' . ++$i;
    }

    \TheRat\SymDep\runCommand("mkdir $releasePath");

    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");

    \TheRat\SymDep\runCommand("ln -s $releasePath {{deploy_path}}/release");
})->desc('Prepare release');

task('deploy-on-prod:update_code', function () {
    $repository = get('repository');
    $branch = env('branch');
    if (input()->hasOption('tag')) {
        $tag = input()->getOption('tag');
    }

    $at = '';
    if (!empty($tag)) {
        $at = "-b $tag";
    } else if (!empty($branch)) {
        $at = "-b $branch";
    }

    \TheRat\SymDep\runCommand("git clone $at --depth 1 --recursive -q $repository {{release_path}} 2>&1");
})->desc('Updating code');

/**
 * Create cache dir
 */
task('deploy:create_cache_dir', function () {
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
 * Normalize asset timestamps
 */
task('deploy-on-prod:assets', function () {
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
task('deploy-on-prod:assetic:dump', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} assetic:dump --env={{env}} --no-debug');

})->desc('Dump assets');


/**
 * Warm up cache
 */
task('deploy-on-prod:cache:warmup', function () {

    \TheRat\SymDep\runCommand('{{symfony_console}} cache:warmup  --env={{env}} --no-debug');
    \TheRat\SymDep\runCommand('{{symfony_console}} assets:install --env={{env}} --no-debug');

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('deploy-on-prod:database:migrate', function () {
    if (get('auto_migrate')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-debug --no-interaction');
    }
})->desc('Migrate database');

/**
 * Create symlink to last release.
 */
task('deploy-on-prod:symlink', function () {
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && ln -sfn {{release_path}} current"); // Atomic override symlink.
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && rm release"); // Remove release link.
})->desc('Creating symlink to release');

/**
 * Doctrine cache clear database
 */
task('deploy-on-prod:database:cache-clear', function () {
    if (get('doctrine_cache_clear')) {
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}} --no-debug');
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-query --env={{env}} --no-debug');
        \TheRat\SymDep\runCommand('{{symfony_console}} doctrine:cache:clear-result --env={{env}} --no-debug');
    }
})->desc('Doctrine cache clear');

/**
 * Cleanup old releases.
 */
task('deploy-on-prod:cleanup', function () {
    $releases = \TheRat\SymDep\runCommand('ls {{deploy_path}}/releases')->toArray();
    rsort($releases);

    $keep = get('keep_releases');

    while ($keep > 0) {
        array_shift($releases);
        --$keep;
    }

    foreach ($releases as $release) {
        \TheRat\SymDep\runCommand("rm -rf {{deploy_path}}/releases/$release");
    }

    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ -e release ]; then rm release; fi");
    \TheRat\SymDep\runCommand("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");

})->desc('Cleaning up old releases');

/**
 * Main task
 */
task('deploy-on-prod', [
    'deploy-on-prod:prepare',
    'deploy-on-prod:release',
    'deploy-on-prod:update_code',
    'symdep:create_cache_dir',
    'symdep:shared',
    'symdep:writable',
    'deploy-on-prod:assets',
    'symdep:vendors',
    'deploy-on-prod:cache:warmup',
    'deploy-on-prod:assetic:dump',
    'deploy-on-prod:database:migrate',
    'deploy-on-prod:symlink',
    'deploy-on-prod:database:cache-clear',
    'deploy-on-prod:cleanup',
])->desc('Deploy your project on "prod"');

after('deploy-on-prod', 'success');
