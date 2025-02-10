<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\SyncEntityRecursionExceptionInterface;

/**
 * @internal
 */
class SyncEntityRecursionException extends SyncException implements SyncEntityRecursionExceptionInterface {}
