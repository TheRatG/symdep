<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputOption;
use TheRat\SymDep\BuildType;

option(
    'build-type',
    't',
    InputOption::VALUE_REQUIRED,
    'Deploy strategy (build type), D|T|P',
    BuildType::TYPE_DEV
);

option(
    'skip-branch',
    null,
    InputOption::VALUE_NONE,
    'Skip branch detection'
);

$helper = new BuildType();
$buildType = $helper->getType();

require_once 'recipe/symfony3.php';

// Environment vars
set('env', []);
set('build_type', $buildType);
set('symfony_env', '{{build_type}}');

require_once 'general.php';
require_once __DIR__ . '/' . $helper->getRecipeFile($buildType);
