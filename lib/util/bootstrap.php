<?php

namespace Lkrms\Util;

use Lkrms\Cli\Cli;
use Lkrms\Env;
use Lkrms\Err\Err;
use Lkrms\Util\Command\Generate\GenerateSyncEntityClass;
use Lkrms\Util\Command\Generate\GenerateSyncEntityInterface;
use Lkrms\Util\Command\Http\HttpGetPath;

$loader = require (
    ($_composer_autoload_path = $_composer_autoload_path ?? "") ?:
    __DIR__ . "/../../vendor/autoload.php"
);
$loader->addPsr4("Lkrms\\Util\\", __DIR__);

Err::handleErrors();

if ($_composer_autoload_path &&
    file_exists($env = dirname(dirname(realpath($_composer_autoload_path))) . "/.env"))
{
    Env::load($env);
}

GenerateSyncEntityClass::register();
GenerateSyncEntityInterface::register();
HttpGetPath::register();

$status = Cli::runCommand();
exit ($status);
