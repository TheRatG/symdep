<?php
$str = '/web/site_rbfx/sites/site.rbfx.co/releases/master';
$sub = "/releases/master/asd";

$res = strpos(strrev($str), strrev($sub));

var_dump($res);

