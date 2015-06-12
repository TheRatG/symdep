<?php
/**
 * Preparing server for deployment.
 */
task('project-update:properties', function () {

    if (env()->has('properties_defined') && env('properties_defined')) {
        return;
    };

    // Symfony shared files
    set('shared_files', []);

    // Environment vars
    $env = 'dev';
    env('env_vars', "SYMFONY_ENV=$env");
    env('env', $env);

    env('release_path', env('deploy_path')); //todo: depends on build type
    env('symfony_console', '{{release_path}}/' . trim(get('bin_dir'), '/') . '/console');

    if (false === env('branch')) {
        env('branch',
            run('cd {{release_path}} && git rev-parse --abbrev-ref HEAD')
                ->toString()
        );
    }

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

/**
 * Create cache dir
 */
task('project-update:create_cache_dir', function () {
    // Set cache dir
    env('cache_dir', trim(get('var_dir'), '/') . '/cache');

    // Remove cache dir if it exist
    run('if [ -d "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');

    // Create cache dir
    run('mkdir -p {{cache_dir}}');

    // Set rights
    run("chmod -R g+w {{cache_dir}}");
})->desc('Create cache dir');

/**
 * Normalize asset timestamps
 */
task('project-update:assets', function () {
    $assets = array_map(function ($asset) {
        return "{{release_path}}/$asset";
    }, get('assets'));

    $time = date('Ymdhi.s');

    foreach ($assets as $dir) {
        if (run("if [ -d $(echo $dir) ]; then echo 1; fi")->toBool()) {
            run("find $dir -exec touch -t $time {} ';' &> /dev/null || true");
        }
    }
})->desc('Normalize asset timestamps');

/**
 * Dump all assets to the filesystem
 */
task('project-update:assetic:dump', function () {

    run('{{symfony_console}} assetic:dump --env={{env}} --quiet');
    run('{{symfony_console}} assets:install --symlink --env={{env}} --quiet');

})->desc('Dump assets');

/**
 * Warm up cache
 */
task('project-update:cache:warmup', function () {

    run('{{symfony_console}} cache:warmup  --env={{env}}');

})->desc('Warm up cache');

/**
 * Migrate database
 */
task('project-update:database:migrate', function () {
    if (get('auto_migrate')) {
        run('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-interaction');
    }
})->desc('Migrate database');

/**
 * Doctrine cache clear database
 */
task('project-update:database:cache-clear', function () {
    if (get('doctrine_cache_clear')) {
        run('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}}');
        run('{{symfony_console}} doctrine:cache:clear-query --env={{env}}');
        run('{{symfony_console}} doctrine:cache:clear-result --env={{env}}');
    }
})->desc('Doctrine cache clear');
