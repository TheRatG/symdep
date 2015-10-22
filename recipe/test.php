<?php
/**
 * Preparing server for deployment.
 */
task('deploy-on-test:properties', function () {

    // Symfony shared files
    set('shared_files', ['app/config/parameters.yml']);

    // Environment vars
    env('env_real', 'test');
    $env = 'test';
    if ('master' == env('branch')) {
        $env = 'prod';
    }
    env('env_vars', "SYMFONY_ENV=$env");
    env('env', $env);

    // Composer install --no-dev
    env('composer_no_dev', 'prod' === $env);

    $deployPath = env()->parse('{{deploy_path}}');
    $sub = "/releases/" . strtolower(env('branch'));
    if (0 === strpos(strrev($deployPath), strrev($sub))) {
         $releasePath = $deployPath;
        $deployPath = dirname(dirname($releasePath));
    } else {
        $releasePath = $deployPath . $sub;
    }
    env('deploy_path', $deployPath);
    env('release_path', $releasePath);
    cd('{{release_path}}');

    env('lock_dir', env('deploy_path'));
    env('current_path', env('release_path'));
})->desc('Preparing server for deploy');

task('deploy-on-test:update_code', function () {
    $releasePath = env('release_path');
    $releasesDir = dirname($releasePath);
    $repository = get('repository');
    $branch = env('branch');

    if (\TheRat\SymDep\dirExists($releasesDir)) {
        run("cd $releasesDir && git pull origin $branch --quiet");
    } else {
        run("mkdir -p $releasesDir");
        run("cd $releasesDir && git clone -b $branch --depth 1 --recursive -q $repository $releasePath");
    }
})->desc('Updating code');

/**
 * Create symlinks for shared directories and files.
 */
task('deploy-on-test:shared', function () {
    $sharedPath = "{{release_path}}/shared";

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

    $masterSharedPath = $sharedPath;
    if ('master' != env('branch')) {
        $masterSharedPath = env()->parse('{{deploy_path}}') . "/releases/master";
    }

    $sharedFiles = get('shared_files');
    $sharedFiles[] = 'app/config/_secret.yml';
    foreach ($sharedFiles as $file) {
        // Remove from source
        run("if [ -f $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi");

        // Create dir of shared file
        run("mkdir -p $sharedPath/" . dirname($file));

        //Copy master shared file
        if (!\TheRat\SymDep\fileExists("$sharedPath/$file")
            && \TheRat\SymDep\fileExists("$masterSharedPath/$file")
        ) {
            run("cp $masterSharedPath/$file $sharedPath/$file");
        }
        // Touch shared
        run("touch $sharedPath/$file");

        // Symlink shared dir to release dir
        run("ln -nfs $sharedPath/$file {{release_path}}/$file");
    }
})->desc('Creating symlinks for shared files');
