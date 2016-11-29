<?php
namespace Deployer;

localServer('local')
    ->stage(['local'])
    ->set('deploy_path', __DIR__);
