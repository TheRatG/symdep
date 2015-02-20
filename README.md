# SymDep

Often I execute a lot of  similar commands for update my local project, thats why this project was created.

## Requirements

Any **symfony** project.

## Install

```bash
composer require phpseclib/phpseclib 2.0.*@dev
composer require therat/symdep 2.0.*@dev
```

Create deploy.php file into your project

```php
<?php
require 'vendor/therat/symdep/recipe/local.php';

// Just stub, use dep local fro update your symfony project
server('local', 'localhost', 22)
    ->path(__DIR__)
    ->user('vagrant', 'vagrant')
    ->setWwwUser('vagrant');
set('repository', '');
// ----

//Helper task
task('local:start', function () {
})->desc('Helper task start');

task('local:end', function () {
})->desc('Helper task end');
```