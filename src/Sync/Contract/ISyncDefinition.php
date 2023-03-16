<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Closure;
use Lkrms\Contract\IImmutable;
use Lkrms\Sync\Support\SyncOperation;

/**
 * Provides direct access to an ISyncProvider's implementation of sync
 * operations for an entity
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of ISyncProvider
 */
interface ISyncDefinition extends IImmutable
{
    /**
     * Return a closure that uses the provider to perform a sync operation on
     * the entity
     *
     * @phpstan-param SyncOperation::* $operation
     * @return Closure|null `null` if `$operation` is not supported, otherwise a
     * closure with the correct signature for the sync operation.
     */
    public function getSyncOperationClosure(int $operation): ?Closure;
}
