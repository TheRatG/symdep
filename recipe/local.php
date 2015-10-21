<?php
/**
 * Preparing server for deployment.
 */
task('project-update:properties', function () {

    // Composer install --no-dev
    env('composer_no_dev', input()->getOption('composer-no-dev'));

    // Symfony shared files
    set('shared_files', []);

    // Environment vars
    $env = \TheRat\SymDep\getBuildType();
    env('env_real', $env);
    if ('test' == $env && 'master' == env('branch')) {
        $env = 'prod';
    }
    env('env_vars', "SYMFONY_ENV=$env");
    env('env', $env);

    env('release_path', env('deploy_path'));
    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');
    cd('{{release_path}}');

    env('lock_dir', env('deploy_path'));
    env('current_path', env('release_path'));
})->desc('Preparing server for deploy');

/**
 * Update project code
 */
task('project-update:update_code', function () {
    $branch = env('branch');
    if (false === $branch) {
        $branch = run('cd {{release_path}} && git rev-parse --abbrev-ref HEAD')
            ->toString();
    }
    $res = run("git for-each-ref --format='%(upstream:short)' $(git symbolic-ref HEAD)");
    if ($res) {
        run("git pull origin $branch 2>&1");
    } else {
        writeln("<comment>Found local git branch. Pulling skipped.</comment>");
    }
})->desc('Updating code');
