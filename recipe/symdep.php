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

$helper = new BuildType();
$buildType = $helper->getType();

set('build_type', $buildType);

require_once 'recipe/symfony3.php';
require_once 'tasks.php';
require_once $helper->getRecipeFile($buildType);
