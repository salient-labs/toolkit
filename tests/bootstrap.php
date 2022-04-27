<?php

$loader = require (__DIR__ . "/../vendor/autoload.php");
$loader->addPsr4("Lkrms\\LkUtil\\", __DIR__ . "/../lib/lk-util");
$loader->addPsr4("Lkrms\\Tests\\", __DIR__);
