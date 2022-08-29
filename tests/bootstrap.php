<?php

$loader = require dirname(__DIR__) . "/vendor/autoload.php";
$loader->addPsr4("Lkrms\\LkUtil\\", dirname(__DIR__) . "/lib/lk-util");
$loader->addPsr4("Lkrms\\Tests\\", __DIR__);
