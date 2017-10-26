<?php
namespace Deployer;

localhost('local')
    ->stage('local')
    ->set('deploy_path', __DIR__);
