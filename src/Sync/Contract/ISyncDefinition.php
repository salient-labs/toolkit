<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IImmutable;
use Lkrms\Sync\Catalog\SyncOperation;
use Closure;

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
     * @param int&SyncOperation::* $operation
     * @return Closure|null `null` if `$operation` is not supported, otherwise a
     * closure with the correct signature for the sync operation.
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
    public function getSyncOperationClosure(int $operation): ?Closure;
}
