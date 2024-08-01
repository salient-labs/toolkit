<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\SyncEntityRecursionExceptionInterface;

/**
 * Thrown when entity hydration triggers infinite recursion
 */
class SyncEntityRecursionException extends AbstractSyncException implements SyncEntityRecursionExceptionInterface {}
