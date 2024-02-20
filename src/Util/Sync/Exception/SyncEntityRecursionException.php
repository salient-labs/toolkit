<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

/**
 * Thrown when entity hydration triggers infinite recursion
 */
class SyncEntityRecursionException extends SyncException {}
