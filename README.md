# SymDep

Often I execute a lot of  similar commands for update my local project, thats why this project was created.

[![Latest Stable Version](https://poser.pugx.org/therat/symdep/v/stable.svg)](https://packagist.org/packages/therat/symdep) 
[![Total Downloads](https://poser.pugx.org/therat/symdep/downloads.svg)](https://packagist.org/packages/therat/symdep) 
[![Latest Unstable Version](https://poser.pugx.org/therat/symdep/v/unstable.svg)](https://packagist.org/packages/therat/symdep) 
[![License](https://poser.pugx.org/therat/symdep/license.svg)](https://packagist.org/packages/therat/symdep)

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/38683099-7e9e-4323-8b41-b0be255e7dc9/big.png)](https://insight.sensiolabs.com/projects/38683099-7e9e-4323-8b41-b0be255e7dc9)


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
