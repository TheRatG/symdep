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

    env('release_path', env()->parse('{{deploy_path}}') . "/releases/" . strtolower(env('branch')));

})->desc('Preparing server for deploy');

task('deploy-on-test:update_code', function () {
    $releasePath = env('release_path');
    $repository = get('repository');
    $branch = env('branch');

    if (\TheRat\SymDep\dirExists($releasePath)) {
        run("cd $releasePath && git pull origin $branch --quiet");
    } else {
        run("mkdir -p $releasePath");
        run("cd $releasePath && git clone -b $branch --depth 1 --recursive -q $repository $releasePath");
    }
})->desc('Updating code');
