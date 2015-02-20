<?php
require 'vendor/therat/symdep/recipe/local.php';

// Just stub, use dep local fro update your symfony project
server('local', 'localhost', 22)
    ->path(__DIR__)
    ->user('vagrant', 'vagrant')
    ->setWwwUser('vagrant');
set('repository', '');
// ----

