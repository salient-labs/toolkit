<?php declare(strict_types=1);

namespace Salient\Tests\Core\EventDispatcher;

use Salient\Contract\Core\Nameable;
use Closure;

/** @var Closure(object $event, mixed[] &$log): void $logger */
return function (
    MainEvent|NamedEvent|(LoggableEvent&Nameable) $event
) use (&$logMultipleEvents, $logger) {
    $logger($event, $logMultipleEvents);
};
