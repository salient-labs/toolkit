<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\InvalidFilterExceptionInterface;

/**
 * @internal
 */
class InvalidFilterException extends SyncException implements InvalidFilterExceptionInterface {}
