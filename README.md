# SymDep

Often I execute a lot of  similar commands for update my local project, thats why this project was created.

[![Latest Stable Version](https://poser.pugx.org/therat/symdep/v/stable.svg)](https://packagist.org/packages/therat/symdep) 
[![Total Downloads](https://poser.pugx.org/therat/symdep/downloads.svg)](https://packagist.org/packages/therat/symdep) 
[![Latest Unstable Version](https://poser.pugx.org/therat/symdep/v/unstable.svg)](https://packagist.org/packages/therat/symdep) 
[![License](https://poser.pugx.org/therat/symdep/license.svg)](https://packagist.org/packages/therat/symdep)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/38683099-7e9e-4323-8b41-b0be255e7dc9/big.png)](https://insight.sensiolabs.com/projects/38683099-7e9e-4323-8b41-b0be255e7dc9)


## Requirements

Any **symfony** project.

## Install

```bash
composer require therat/symdep ~3.0
```

Create deploy.php file into your project

```bash
cp vendor/therat/symdep/deploy.php.example deploy.php
```

Add file `symdep.lock` to your `.gitignore`

## Extend tasks

### Modify properties

Example 

```
/**
 * modify properties
 */
task(
    'env',
    function () {
        switch (get('build_type')) {
            case BuildType::TYPE_DEV:
                break;
            case BuildType::TYPE_TEST:
                break;
            case BuildType::TYPE_PROD:
                break;
        }
    }
);
after('properties', 'env');
```

### Delete useless branch folder from test

```bash
./bin/project-drop-branches-from-test
```

## Build new version of geggs

* Create and push tag
* Create phar 
```
./bin/box-build
```
* Go to github and upload new `symdep.phar` into new release
* Publish new manifest
```
./bin/generate-manifest
```
