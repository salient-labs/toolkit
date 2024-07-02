<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Core\Immutable;
use Salient\Contract\Sync\SyncOperation as OP;
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
     * Get a closure to perform a sync operation on the entity, or null if the
     * operation is not supported
     *
     * @template TOperation of OP::*
     *
     * @param TOperation $operation
     * @return (
     *     TOperation is OP::READ
     *     ? (Closure(SyncContextInterface, int|string|null, mixed...): TEntity)
     *     : (
     *         TOperation is OP::READ_LIST
     *         ? (Closure(SyncContextInterface, mixed...): iterable<TEntity>)
     *         : (
     *             TOperation is OP::CREATE|OP::UPDATE|OP::DELETE
     *             ? (Closure(SyncContextInterface, TEntity, mixed...): TEntity)
     *             : (Closure(SyncContextInterface, iterable<TEntity>, mixed...): iterable<TEntity>)
     *         )
     *     )
     * )|null
     */
    public function getOperationClosure($operation): ?Closure;
}
