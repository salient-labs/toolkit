<?php

namespace Lkrms\LkUtil;

use Lkrms\App\App;
use Lkrms\Cli\Cli;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntityClass;
use Lkrms\LkUtil\Command\Generate\GenerateSyncEntityInterface;
use Lkrms\LkUtil\Command\Http\HttpGetPath;
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

GenerateSyncEntityClass::register(["generate", "sync-entity"]);
GenerateSyncEntityInterface::register(["generate", "sync-entity-provider"]);
HttpGetPath::register(["http", "get"]);

$status = Cli::run();
exit ($status);
