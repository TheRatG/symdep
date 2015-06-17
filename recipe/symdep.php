<?php
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/** @var Symfony\Component\Console\Input\InputDefinition $input */
$inputDefinition = \Deployer\Deployer::get()->getConsole()->getDefinition();
if (!$inputDefinition->hasArgument('stage')) {
    $inputDefinition->addArgument(new InputArgument('stage', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Run tasks only on this server or group of servers.', 'local'));
}
if (!$inputDefinition->hasArgument('branch')) {
    $inputDefinition->addArgument(new InputArgument('branch', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Release branch'));
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

before('properties', 'check_connection');

//For run main command separately
before('install', 'properties');
before('configure', 'properties');
before('link', 'properties');

switch ($buildType) {
    case \TheRat\SymDep\BUILD_TYPE_DEV:
        require_once 'local.php';

        after('properties', 'project-update:properties');
        after('install', 'project-update:update_code');
        after('install', 'create_cache_dir');
        after('install', 'shared');
        after('install', 'writable');
        after('install', 'assets');
        after('install', 'vendors');

        after('configure', 'assetic:dump');
        after('configure', 'cache:warmup');
        after('configure', 'database:migrate');
        after('link', 'database:cache-clear');

        break;
    case \TheRat\SymDep\BUILD_TYPE_TEST:
        require_once 'test.php';

        after('properties', 'deploy-on-test:properties');
        after('install', 'deploy-on-test:update_code');
        after('install', 'create_cache_dir');
        after('install', 'shared');
        after('install', 'writable');
        after('install', 'assets');
        after('install', 'vendors');

        after('configure', 'assetic:dump');
        after('configure', 'cache:warmup');
        after('configure', 'database:migrate');
        after('link', 'database:cache-clear');
        break;
    case \TheRat\SymDep\BUILD_TYPE_PROD:
        require_once 'prod.php';

        after('properties', 'deploy-on-prod:properties');
        after('properties', 'deploy-on-prod:update_code');

        after('install', 'create_cache_dir');
        after('install', 'shared');
        after('install', 'writable');
        after('install', 'assets');
        after('install', 'vendors');

        after('configure', 'assetic:dump');
        after('configure', 'cache:warmup');
        after('configure', 'database:migrate');

        after('link', 'deploy-on-prod:symlink');
        after('link', 'database:cache-clear');
        after('link', 'deploy-on-prod:cleanup');

        after('rollback', 'deploy-on-prod:rollback');
        break;
    default:
        throw new \RuntimeException('Invalid strategy value, must be D | T | P');
}
