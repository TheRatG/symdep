<?php
set('repository', '<project_repository_url>');

// Just stub, use `symdep project-update dev`
server('local', 'localhost', 22)
    ->stage('dev')
    ->env('deploy_path', __DIR__);

server('test', '<test_host>', 22)
    ->user('tcrm')
    ->identityFile()
    ->stage('test')
    ->env('deploy_path', '</your/project/path>');

server('prod', '<test_host>', 22)
    ->user('crm')
    ->forwardAgent()
    ->stage('prod')
    ->env('deploy_path', '</your/project/path>');

// ----- local ------
task('local:prepare:env', function () {
    // Symfony shared files
    set('shared_files', ['app/config/parameters.yml', 'app/config/secret.yml']);
});

task('local:generate-files', function () {
    $srcDir = env()->parse('{{release_path}}/deploy/templates');
    $dstDir = env()->parse('{{release_path}}');
    \TheRat\SymDep\generateFiles($srcDir, $dstDir, true);

    \TheRat\SymDep\generateFile(
        '{{release_path}}/deploy/templates_env/etc/crontab_{{env}}.conf',
        '{{deploy_path}}/etc/crontab.conf',
        null,
        true
    );
});
task('local:configure-after', function () {
    //runLocally('{{symfony_console}} my:command');
});

after('local:prepare', 'local:prepare:env');
before('local:vendors', 'local:generate-files');
after('local:cache:warmup', 'local:configure-after');
