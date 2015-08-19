<?php
set('repository', '<project_repository_url>');

// Just stub, use `symdep project-update dev`
server('local', 'localhost', 22)
    ->stage('dev')
    ->env('deploy_path', __DIR__);

//server('test', '<test_host>', 22)
//    ->user('tcrm')
//    ->identityFile()
//    ->stage('test')
//    ->env('deploy_path', '</your/project/path>');
//
//server('prod', '<test_host>', 22)
//    ->user('crm')
//    ->forwardAgent()
//    ->stage('prod')
//    ->env('deploy_path', '</your/project/path>');
//
//task('tasks:generate-files', function () {
//    $srcDir = env()->parse('{{release_path}}/deploy/templates');
//    $dstDir = env()->parse('{{release_path}}');
//    \TheRat\SymDep\generateFiles($srcDir, $dstDir, get('locally'));
//
//    \TheRat\SymDep\generateFile(
//        '{{release_path}}/deploy/templates_env/etc/crontab_{{env}}.conf',
//        '{{deploy_path}}/etc/crontab.conf',
//        null,
//        true
//    );
//});
//task('tasks:configure-after', function () {
//    //\TheRat\SymDep\runCommand('{{symfony_console}} <your_scripts>', get('locally'));
//});
//
////--- local ---
//task('tasks:env', function () {
//    // Symfony shared files
//    set('shared_files', ['app/config/parameters.yml', 'app/config/secret.yml']);
//});
//after('local:prepare', 'tasks:env');
//before('symdep:vendors', 'tasks:generate-files');
//after('local:cache:warmup', 'tasks:configure-after');
//
//after('deploy-on-test:prepare', 'tasks:env');
//after('deploy-on-prod:prepare', 'tasks:env');
//
//before('symdep:vendors', 'tasks:generate-files');
//
//after('deploy-on-test:cache:warmup', 'tasks:configure-after');
//after('deploy-on-prod:cache:warmup', 'tasks:configure-after');
