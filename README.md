# SymDep

Often I execute a lot of  similar commands for update my local project, thats why this project was created.

## Requirements

Any **symfony** project.
PHP extension ssh2.

## Install

```bash
composer require phpseclib/phpseclib 2.0.*@dev
composer require therat/symdep 2.0.*@dev
```

Create deploy.php file into your project

```bash
cp vendor/therat/symdep/deploy.php.example deploy.php
```

### Configure Mac Os

Install or update Mac Port https://www.macports.org/install.php

```bash
#install libssh2
sudo port install libssh2

#install PHP extension ssh2.
sudo pecl install channel://pecl.php.net/ssh2-0.12
```
