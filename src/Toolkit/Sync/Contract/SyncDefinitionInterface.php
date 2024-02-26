<?php declare(strict_types=1);

namespace Salient\Sync\Contract;

use Salient\Core\Contract\Immutable;
use Salient\Sync\Catalog\SyncOperation;
use Closure;

/**
 * Provides direct access to a provider's implementation of sync operations for
 * an entity
 *
 * @template TEntity of SyncEntityInterface
 * @template TProvider of SyncProviderInterface
 */
interface SyncDefinitionInterface extends Immutable
{
    /**
     * Get a closure that uses the provider to perform a sync operation on the
     * entity
     *
     * @param SyncOperation::* $operation
     * @return (Closure(SyncContextInterface, mixed...): (iterable<TEntity>|TEntity))|null `null` if `$operation` is not supported, otherwise a closure with the correct signature for the sync operation.
     * @phpstan-return (
     *     $operation is SyncOperation::READ
     *     ? (Closure(SyncContextInterface, int|string|null, mixed...): TEntity)
     *     : (
     *         $operation is SyncOperation::READ_LIST
     *         ? (Closure(SyncContextInterface, mixed...): iterable<TEntity>)
     *         : (
     *             $operation is SyncOperation::CREATE|SyncOperation::UPDATE|SyncOperation::DELETE
     *             ? (Closure(SyncContextInterface, TEntity, mixed...): TEntity)
     *             : (Closure(SyncContextInterface, iterable<TEntity>, mixed...): iterable<TEntity>)
     *         )
     *     )
     * )|null
     */
    public function getSyncOperationClosure($operation): ?Closure;
}
