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

(Cli::load()
    ->enableExistingCache()
    ->enableMessageLog()
    ->command(["generate", "facade"], GenerateFacadeClass::class)
    ->command(["generate", "sync", "entity"], GenerateSyncEntityClass::class)
    ->command(["generate", "sync", "provider"], GenerateSyncEntityInterface::class)
    ->command(["heartbeat"], CheckHeartbeat::class)
    ->command(["http", "get"], SendHttpRequest::class)
    ->command(["http", "head"], SendHttpRequest::class)
    ->command(["http", "post"], SendHttpRequest::class)
    ->command(["http", "put"], SendHttpRequest::class)
    ->command(["http", "delete"], SendHttpRequest::class)
    ->command(["http", "patch"], SendHttpRequest::class)
    ->runAndExit());
