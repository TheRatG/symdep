<?php
/**
 * Preparing server for deployment.
 */
task('deploy-on-prod:properties', function () {

    // Deploy branch
    $branch = input()->getArgument('branch');
    if (!$branch) {
        $branch = 'master';
    }
    env('branch', $branch);

    // Environment vars
    $env = 'prod';
    env('env_vars', "SYMFONY_ENV=$env");
    env('env', $env);
    env('env_real', $env);

    // Symfony shared files
    set('shared_files', ['app/config/parameters.yml', 'app/config/_secret.yml']);

    if (!\Deployer\Task\Context::get()->getEnvironment()->has('current')
        && \TheRat\SymDep\fileExists('{{deploy_path}}/current')
    ) {
        $current = run("readlink {{deploy_path}}/current")->toString();
        env('current', $current);
    }

    if (!\Deployer\Task\Context::get()->getEnvironment()->has('release_path')) {
        $release = run('date +%Y%m%d%H%M%S');
        $releasePath = env()->parse('{{deploy_path}}') . "/releases/" . $release;
        if (in_array('local', env('stages'))) {
            $releasePath = env()->parse('{{deploy_path}}');
        }
        env('release_path', $releasePath);
    }
    cd('{{release_path}}');

    env('lock_dir', env('deploy_path'));
    env('current_path', env('deploy_path') . '/current');
})->desc('Preparing server for deploy');

/**
 * Update project code
 */
task('deploy-on-prod:update_code', function () {

    $releasePath = env('release_path');
    $i = 0;
    while (is_dir(env()->parse($releasePath)) && $i < 42) {
        $releasePath .= '.' . ++$i;
    }
    run("mkdir -p $releasePath");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");
    run("ln -s $releasePath {{deploy_path}}/release");

    $repository = get('repository');
    $branch = env('branch');
    $at = '';
    if (!empty($branch)) {
        $at = "-b $branch";
    }
    run("git clone $at --depth 1 --recursive -q $repository {{release_path}} 2>&1");

})->desc('Updating code');

/**
 * Create symlink to last release.
 */
task('deploy-on-prod:link', function () {
    run("cd {{deploy_path}} && ln -sfn {{release_path}} current"); // Atomic override symlink.
    run("cd {{deploy_path}} && rm release"); // Remove release link.
})->desc('Creating symlink to release');

/**
 * Cleanup old releases.
 */
task('deploy-on-prod:cleanup', function () {
    $releases = run('ls {{deploy_path}}/releases')->toArray();
    rsort($releases);

    $keep = get('keep_releases');

    while ($keep > 0) {
        array_shift($releases);
        --$keep;
    }

    foreach ($releases as $release) {
        run("rm -rf {{deploy_path}}/releases/$release");
    }

    run("cd {{deploy_path}} && if [ -e release ]; then rm release; fi");
    run("cd {{deploy_path}} && if [ -h release ]; then rm release; fi");

})->desc('Cleaning up old releases');

/**
 * Rollback to previous release.
 */
task('deploy-on-prod:rollback', function () {
    $releases = env('releases_list');

    if (isset($releases[1])) {
        $releaseDir = "{{deploy_path}}/releases/{$releases[1]}";

        // Symlink to old release.
        run("cd {{deploy_path}} && ln -nfs $releaseDir current");

        // Remove release
        run("rm -rf {{deploy_path}}/releases/{$releases[0]}");

        if (isVerbose()) {
            writeln("Rollback to `{$releases[1]}` release was successful.");
        }
    } else {
        writeln("<comment>No more releases you can revert to.</comment>");
    }
})->desc('Rollback to previous release');
