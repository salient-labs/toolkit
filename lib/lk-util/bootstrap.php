<?php

namespace Lkrms\LkUtil;

use Lkrms\Cli\Cli;
use Lkrms\Err\Err;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntityClass;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntityInterface;
use Lkrms\LkUtil\Command\Http\HttpGetPath;
use Lkrms\Util\Env;

$loader = require (
    ($_composer_autoload_path = $_composer_autoload_path ?? "") ?:
    __DIR__ . "/../../vendor/autoload.php"
);
$loader->addPsr4("Lkrms\\LkUtil\\", __DIR__);

Err::handleErrors();

if ($_composer_autoload_path &&
    file_exists($env = dirname(dirname(realpath($_composer_autoload_path))) . "/.env"))
{
    Env::load($env);
}

GenerateSyncEntityClass::register(["generate", "sync-entity"]);
GenerateSyncEntityInterface::register(["generate", "sync-entity-provider"]);
HttpGetPath::register(["http", "get"]);

$status = Cli::run();
exit ($status);
