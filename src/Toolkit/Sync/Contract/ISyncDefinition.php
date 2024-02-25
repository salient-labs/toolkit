<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Sync\Catalog\SyncOperation;
use Salient\Core\Contract\Immutable;
use Closure;

/**
 * Provides direct access to an ISyncProvider's implementation of sync
 * operations for an entity
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of ISyncProvider
 */
interface ISyncDefinition extends Immutable
{
    /**
     * Get a closure that uses the provider to perform a sync operation on the
     * entity
     *
     * @param SyncOperation::* $operation
     * @return (Closure(ISyncContext, mixed...): (iterable<TEntity>|TEntity))|null `null` if `$operation` is not supported, otherwise a closure with the correct signature for the sync operation.
     * @phpstan-return (
     *     $operation is SyncOperation::READ
     *     ? (Closure(ISyncContext, int|string|null, mixed...): TEntity)
     *     : (
     *         $operation is SyncOperation::READ_LIST
     *         ? (Closure(ISyncContext, mixed...): iterable<TEntity>)
     *         : (
     *             $operation is SyncOperation::CREATE|SyncOperation::UPDATE|SyncOperation::DELETE
     *             ? (Closure(ISyncContext, TEntity, mixed...): TEntity)
     *             : (Closure(ISyncContext, iterable<TEntity>, mixed...): iterable<TEntity>)
     *         )
     *     )
     * )|null
     */
    public function getSyncOperationClosure($operation): ?Closure;
}
