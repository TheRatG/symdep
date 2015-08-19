<?php
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/** @var Symfony\Component\Console\Input\InputDefinition $input */
$inputDefinition = \Deployer\Deployer::get()->getConsole()->getDefinition();
if (!$inputDefinition->hasArgument('stage')) {
    $inputDefinition->addArgument(new InputArgument('stage', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Run tasks only on this server or group of servers.', 'dev'));
}
if (!$inputDefinition->hasArgument('branch')) {
    $inputDefinition->addArgument(new InputArgument('branch', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Release branch', 'master'));
}
if (!$inputDefinition->hasOption('build-type')) {
    $inputDefinition->addOption(new InputOption('build-type', 't', \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Deploy strategy (build type), D|T|P', \TheRat\SymDep\BUILD_TYPE_DEV));
}
if (!$inputDefinition->hasOption('composer-no-dev')) {
    $inputDefinition->addOption(new InputOption('composer-no-dev', 'C', \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Composer --no-dev'));
}
$buildType = \TheRat\SymDep\getBuildType();

require_once 'common.php';

task('deploy', [
    'install',
    'configure',
    'link',
])
    ->desc('Default command, depend on --build-type=<[d]ev|[t]est|[p]rod>');

switch ($buildType) {
    case \TheRat\SymDep\BUILD_TYPE_DEV:
        require_once 'local.php';

        before('properties', 'check_connection');
        after('properties', 'project-update:properties');

        before('install', 'install-before');
        before('install', 'properties');
        after('install', 'project-update:update_code');
        after('install', 'create_cache_dir');
        after('install', 'shared');
        after('install', 'writable');
        after('install', 'assets');
        after('install', 'install-after');

        before('configure', 'configure-before');
        before('configure', 'properties');
        after('configure', 'vendors');
        after('configure', 'assetic:dump');
        after('configure', 'cache:warmup');
        after('configure', 'database:migrate');
        after('configure', 'database:cache-clear');
        after('configure', 'configure-after');

        before('link', 'link-before');
        before('link', 'properties');
        after('link', 'link-after');

        break;
    case \TheRat\SymDep\BUILD_TYPE_TEST:
        require_once 'test.php';

        before('properties', 'check_connection');
        after('properties', 'deploy-on-test:properties');

        before('install', 'install-before');
        before('install', 'properties');
        after('install', 'deploy-on-test:update_code');
        after('install', 'create_cache_dir');
        after('install', 'deploy-on-test:shared');
        after('install', 'writable');
        after('install', 'assets');
        after('install', 'install-after');

        before('configure', 'configure-before');
        before('configure', 'properties');
        after('configure', 'vendors');
        after('configure', 'assetic:dump');
        after('configure', 'cache:warmup');
        after('configure', 'database:migrate');
        after('configure', 'database:cache-clear');
        after('configure', 'configure-after');

        before('link', 'link-before');
        before('link', 'properties');
        after('link', 'link-after');
        break;
    case \TheRat\SymDep\BUILD_TYPE_PROD:
        require_once 'prod.php';

        before('properties', 'check_connection');
        after('properties', 'deploy-on-prod:properties');

        before('install', 'install-before');
        before('install', 'properties');
        after('install', 'deploy-on-prod:update_code');
        after('install', 'create_cache_dir');
        after('install', 'shared');
        after('install', 'writable');
        after('install', 'assets');
        after('install', 'install-after');

        before('configure', 'configure-before');
        before('configure', 'properties');
        after('configure', 'vendors');
        after('configure', 'assetic:dump');
        after('configure', 'cache:warmup');
        after('configure', 'database:migrate');
        after('configure', 'database:cache-clear');
        after('configure', 'configure-after');

        before('link', 'link-before');
        before('link', 'properties');
        after('link', 'deploy-on-prod:link');
        after('link', 'deploy-on-prod:cleanup');
        after('link', 'link-after');

        after('rollback', 'deploy-on-prod:rollback');
        break;
    default:
        throw new \RuntimeException('Invalid strategy value, must be D | T | P');
}
