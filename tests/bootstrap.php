<?php

$loader = require (__DIR__ . "/../vendor/autoload.php");
$loader->addPsr4("Lkrms\\Util\\", __DIR__ . "/../lib/util");
$loader->addPsr4("Lkrms\\Tests\\", __DIR__);
