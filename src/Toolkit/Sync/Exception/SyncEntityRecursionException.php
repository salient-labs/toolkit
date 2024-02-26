<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

/**
 * Thrown when entity hydration triggers infinite recursion
 */
class SyncEntityRecursionException extends AbstractSyncException {}
