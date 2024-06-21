<?php declare(strict_types=1);

namespace Salient\Tests\Core\EventDispatcher;

use Salient\Contract\Core\HasName;
use Closure;

/** @var Closure(object $event, mixed[] &$log): void $logger */
return function (
    MainEvent|NamedEvent|(LoggableEvent&HasName) $event
) use (&$logMultipleEvents, $logger) {
    $logger($event, $logMultipleEvents);
};
