<?php
task('properties', function () {

    // Keep releases
    set('keep_releases', 5);

    // Composer install --no-dev
    env('composer_no_dev', true);

    // Symfony shared dirs
    set('shared_dirs', ['app/logs', 'web/uploads', 'app/sessions']);

    // Symfony writable dirs
    set('writable_dirs', ['app/cache', 'app/logs', 'web/uploads']);

    // Assets
    set('assets', ['web/css', 'web/images', 'web/js']);

    // Auto migrate
    set('auto_migrate', true);

    //Doctrine cache clear
    set('doctrine_cache_clear', true);

    set('bin_dir', 'app');

    set('var_dir', 'app');

    //console
    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');

    // Deploy branch
    $branch = input()->getArgument('branch');
    if (!$branch) {
        $branch = runLocally('git rev-parse --abbrev-ref HEAD')
            ->toString();
    }
    env('branch', $branch);

    env('lock_keep', 15);
    env('lock_dir', '');
    env('lock_wait', input()->getOption('lock-wait'));

})->desc('1. Prepare environment properties');

task('install-before', function () {

})->desc('2. Before install');

task('install', function () {

})->desc('3. Deploy and prepare files');

task('install-after', function () {

})->desc('4. After install');

task('configure-before', function () {

})->desc('5. Before configure');

task('configure', function () {

})->desc('6. Run necessary scripts for project');

task('configure-after', function () {

})->desc('7. After configure');

task('link-before', function () {

})->desc('8. Before link');

task('link', function () {

})->desc('9. Change symlinks');

task('link-after', function () {

})->desc('10. after link');

task('rollback', function () {

})->desc('Delete deploy ');

task('check_connection', function () {
    \Deployer\Task\Context::get()->getServer()->connect();

    // Check if shell is POSIX-compliant
    try {
        cd(''); // To run command as raw.
        $result = run('echo $0')->toString();
        if ($result == 'stdin: is not a tty') {
            throw new RuntimeException(
                "Looks like ssh inside another ssh.\n" .
                "Help: http://goo.gl/gsdLt9"
            );
        }
    } catch (\RuntimeException $e) {
        $formatter = \Deployer\Deployer::get()->getHelper('formatter');
        $errorMessage = [
            "Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.",
            "Usually, you can change your shell to bash by running: chsh -s /bin/bash",
        ];
        write($formatter->formatBlock($errorMessage, 'error', true));
        throw $e;
    }
});

/**
 * Create symlinks for shared directories and files.
 */
task('shared', function () {
    $sharedPath = "{{deploy_path}}/shared";

    foreach (get('shared_dirs') as $dir) {
        // Remove from source
        run("if [ -d $(echo {{release_path}}/$dir) ]; then rm -rf {{release_path}}/$dir; fi");

        // Create shared dir if it does not exist
        run("mkdir -p $sharedPath/$dir");

        // Create path to shared dir in release dir if it does not exist
        // (symlink will not create the path and will fail otherwise)
        run("mkdir -p `dirname {{release_path}}/$dir`");

        // Symlink shared dir to release dir
        run("ln -nfs $sharedPath/$dir {{release_path}}/$dir");
    }

    $sharedFiles = get('shared_files');
    $sharedFiles[] = 'app/config/_secret.yml';
    foreach ($sharedFiles as $file) {
        // Remove from source
        run("if [ -f $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi");

        // Create dir of shared file
        run("mkdir -p $sharedPath/" . dirname($file));

        // Touch shared
        run("touch $sharedPath/$file");

        // Symlink shared dir to release dir
        run("ln -nfs $sharedPath/$file {{release_path}}/$file");
    }

    if (!\TheRat\SymDep\fileExists('{{release_path}}/app/config/_secret.yml')) {
        run('touch {{release_path}}/app/config/_secret.yml');
    }
})->desc('Creating symlinks for shared files');

/**
 * Create cache dir
 */
task('create_cache_dir', function () {
    // Set cache dir
    env('cache_dir', '{{release_path}}/' . trim(get('var_dir'), '/') . '/cache');

    // Remove cache dir if it exist
    run('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');

    // Create cache dir
    run('mkdir -p {{cache_dir}}');

    // Set rights
    run("chmod -R g+w {{cache_dir}}");
})->desc('Create cache dir');

/**
 * Make writable dirs.
 */
task('writable', function () {
    $dirs = join(' ', get('writable_dirs'));

    if (!empty($dirs)) {
        run("cd {{release_path}} && chmod 777 $dirs");
    }

})->desc('Make writable dirs');

/**
 * Normalize asset timestamps
 */
task('assets', function () {
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
 * Installing vendors tasks.
 */
task('vendors', function () {
    if (run("if hash composer 2>/dev/null; then echo 'true'; fi")->toBool()) {
        $composer = 'composer';
    } else {
        run("cd {{release_path}} && curl -sS https://getcomposer.org/installer | php");
        $composer = 'php composer.phar';
    }

    $require = env('composer_no_dev') ? '--no-dev' : '--dev';
    $options = "--prefer-dist --optimize-autoloader --no-progress --no-interaction $require";

    run("cd {{release_path}} && {{env_vars}} $composer install $options");
    run("cd {{release_path}} && {{env_vars}} $composer dump-autoload");

})->desc('Installing vendors');

/**
 * Dump all assets to the filesystem
 */
task('assetic:dump', function () {

    run('cd {{release_path}} && {{symfony_console}} assetic:dump --env={{env}}');
    run('cd {{release_path}} && {{symfony_console}} assets:install --symlink --env={{env}}');

})->desc('Dump assets');


/**
 * Warm up cache
 */
task('cache:warmup', function () {

    run('{{symfony_console}} cache:warmup  --env={{env}}');

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('database:migrate', function () {
    if (get('auto_migrate')) {
        run('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-debug --no-interaction');
    }
})->desc('Migrate database');

/**
 * Doctrine cache clear database
 */
task('database:cache-clear', function () {
    if (get('doctrine_cache_clear')) {
        run('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}} --no-debug');
        run('{{symfony_console}} doctrine:cache:clear-query --env={{env}} --no-debug');
        run('{{symfony_console}} doctrine:cache:clear-result --env={{env}} --no-debug');
    }
})->desc('Doctrine cache clear');

task('lock', function () {
    $lockWait = env('lock_wait');
    $filename = env('lock_dir') . '/symdep.lock';
    $locker = new \TheRat\SymDep\Locker($filename, env('lock_keep'));
    $needLock = true;

    if ($locker->isLocked()) {
        if ($lockWait) {
            writeln($locker->__toString());
            $needLock = askConfirmation('Force deploy');
        } else {
            $needLock = false;
        }
    }

    if ($needLock) {
        $locker->lock([
            'date' => trim(run('date -u')->toString()),
            'user' => trim(run('whoami')->toString()),
            'server' => trim(run('uname -a')->toString()),
        ]);
        if (isVerbose()) {
            writeln(sprintf('Create lock file "%s"', $filename));
        }
    } else {
        writeln('<error>Deploy process locked</error>');
        exit(0);
    }
});

task('unlock', function () {
    $filename = env('lock_dir') . '/symdep.lock';
    $locker = new \TheRat\SymDep\Locker($filename, env('lock_keep'));
    $locker->unlock();
});

/**
 * Delete useless branches, which no in remote repository
 */
task('drop-branches', function () {
    if ('test' != env('env_real')) {
        throw new \RuntimeException('This command only for "test" build type');
    }

    $path = env('deploy_path') . '/releases';
    writeln($path);
});
