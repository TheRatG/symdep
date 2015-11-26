<?php
set('repository', '<project_repository_url>');

// Just stub, use `symdep project-update dev`
server('local', 'localhost', 22)
    ->stage('dev')
    ->env('deploy_path', __DIR__);

