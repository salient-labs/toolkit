<?php declare(strict_types=1);

namespace Salient\Tests\Core\EventDispatcher;

use Salient\Contract\Core\HasName;

return fn(LoggableEvent&HasName $event) => null;
