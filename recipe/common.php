<?php
task('properties', function () {

    if (env()->has('properties_defined') && env('properties_defined')) {
        return;
    };

    env('properties_defined', true);

    env('composer_no_dev', input()->getOption('composer-no-dev'));

    env('branch', input()->getOption('branch'));

    // Symfony shared dirs
    set('shared_dirs', ['app/logs', 'web/uploads']);

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

    set('bin_dir', 'app');

    set('var_dir', 'app');

});

task('install', function () {

});

task('configure', function () {

});

task('link', function () {

});

task('check_connection', function () {
    // Check if shell is POSIX-compliant
    try {
        cd(''); // To run command as raw.
        run('echo $0');
    } catch (\RuntimeException $e) {
        /** @var \Symfony\Component\Console\Helper\FormatterHelper $formatter */
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

    foreach (get('shared_files') as $file) {
        // Remove from source
        run("if [ -f $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi");

        // Create dir of shared file
        run("mkdir -p $sharedPath/" . dirname($file));

        // Touch shared
        run("touch $sharedPath/$file");

        // Symlink shared dir to release dir
        run("ln -nfs $sharedPath/$file {{release_path}}/$file");
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
    $options = "--prefer-dist --optimize-autoloader --no-progress --no-interaction --quiet $require";

    run(
        "cd {{release_path}} && {{env_vars}} $composer install $options",
        get('locally')
    );

    sleep(5);

})->desc('Installing vendors');
