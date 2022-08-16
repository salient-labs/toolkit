<?php

namespace Lkrms\LkUtil;

use Lkrms\Facade\Cli;
use Lkrms\LkUtil\Command\CheckHeartbeat;
use Lkrms\LkUtil\Command\Generate\GenerateFacadeClass;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntityClass;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntityInterface;
use Lkrms\LkUtil\Command\Http\SendHttpRequest;

$loader = require (
    ($_composer_autoload_path = $_composer_autoload_path ?? "") ?:
    __DIR__ . "/../../vendor/autoload.php"
);
$loader->addPsr4("Lkrms\\LkUtil\\", __DIR__);

$app = Cli::load();
if ($app->hasCacheStore())
{
    $app->enableCache();
}

$app->command(["generate", "facade"], GenerateFacadeClass::class);
$app->command(["generate", "sync", "entity"], GenerateSyncEntityClass::class);
$app->command(["generate", "sync", "provider"], GenerateSyncEntityInterface::class);
$app->command(["heartbeat"], CheckHeartbeat::class);
$app->command(["http", "get"], SendHttpRequest::class);
$app->command(["http", "head"], SendHttpRequest::class);
$app->command(["http", "post"], SendHttpRequest::class);
$app->command(["http", "put"], SendHttpRequest::class);
$app->command(["http", "delete"], SendHttpRequest::class);
$app->command(["http", "patch"], SendHttpRequest::class);

$status = $app->run();
exit ($status);
