<?php

namespace Lkrms\LkUtil;

use Lkrms\Facade\App;
use Lkrms\Cli\Cli;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntityClass;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntityInterface;
use Lkrms\LkUtil\Command\Http\SendHttpRequest;
use Lkrms\Util\Composer;

$loader = require (
    ($_composer_autoload_path = $_composer_autoload_path ?? "") ?:
    __DIR__ . "/../../vendor/autoload.php"
);
$loader->addPsr4("Lkrms\\LkUtil\\", __DIR__);

$app = App::load(Composer::getRootPackagePath());
if ($app->hasCacheStore())
{
    $app->enableCache();
}

GenerateSyncEntityClass::register($app, ["generate", "sync-entity"]);
GenerateSyncEntityInterface::register($app, ["generate", "sync-entity-provider"]);
SendHttpRequest::register($app, ["http", "get"]);
SendHttpRequest::register($app, ["http", "head"]);
SendHttpRequest::register($app, ["http", "post"]);
SendHttpRequest::register($app, ["http", "put"]);
SendHttpRequest::register($app, ["http", "delete"]);
SendHttpRequest::register($app, ["http", "patch"]);

$status = Cli::run();
exit ($status);
