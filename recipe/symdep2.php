<?php
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasArgument('stage')) {
    argument('stage', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Run tasks only on this server or group of servers.');
}
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasArgument('branch')) {
    argument('branch', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Release branch', 'master');
}
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasOption('strategy')) {
    option('build-type', 'b', \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Deploy strategy (build type), D|T|P', \TheRat\SymDep\BUILD_TYPE_DEV);
}
if (!\Deployer\Deployer::get()->getConsole()->getUserDefinition()->hasOption('strategy')) {
    option('composer-no-dev', 'b', \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Deploy strategy (build type), D|T|P', \TheRat\SymDep\BUILD_TYPE_DEV);
}

$buildType = \TheRat\SymDep\getBuildType();

require_once 'common.php';

before('properties', 'check_connection');

task('deploy', [
    'properties',
    'install',
    'configure',
    'link',
]);

switch ($buildType) {
    case \TheRat\SymDep\BUILD_TYPE_DEV:
        require_once 'local2.php';

        after('properties', 'project-update:properties');
        after('install', 'project-update:update_code');
        after('install', 'create_cache_dir');
        after('install', 'shared');
        after('install', 'writable');
        after('install', 'writable');
        after('install', 'assets');
        after('install', 'vendors');

        after('configure', 'assetic:dump');
        after('configure', 'cache:warmup');
        after('configure', 'database:migrate');
        after('configure', 'database:cache-clear');

        break;
    case \TheRat\SymDep\BUILD_TYPE_TEST:
        require_once 'test2.php';

        after('properties', 'deploy-on-test:properties');
        break;
    case \TheRat\SymDep\BUILD_TYPE_PROD:
        require_once 'prod2.php';

        after('properties', 'deploy-on-prod:properties');
        break;
    default:
        throw new \RuntimeException('Invalid strategy value, must be D | T | P');
}
