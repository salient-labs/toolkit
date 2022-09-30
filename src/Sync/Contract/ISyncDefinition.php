<?php

declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Closure;

/**
 * Provides access to an ISyncProvider's implementation of sync operations for
 * an entity
 *
 */
interface ISyncDefinition
{
    /**
     * Return a closure that uses the provider to perform a sync operation on
     * the entity
     *
     * @return Closure|null `null` if `$operation` is not supported, otherwise a
     * closure with the correct signature for the sync operation.
     */
    public function getSyncOperationClosure(int $operation): ?Closure;

}
