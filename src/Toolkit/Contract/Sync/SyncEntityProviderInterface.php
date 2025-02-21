<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Core\Provider\HasProvider;
use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Contract\HasTextComparisonFlag;
use Closure;

/**
 * Provides an entity-agnostic interface to a SyncProviderInterface's
 * implementation of sync operations for an entity
 *
 * @template TEntity of SyncEntityInterface
 *
 * @extends HasProvider<SyncProviderInterface>
 */
interface SyncEntityProviderInterface extends HasProvider, HasTextComparisonFlag
{
    /**
     * Get the sync entity being serviced
     *
     * @return class-string<TEntity>
     */
    public function entity(): string;

    /**
     * Perform an arbitrary sync operation on one or more backend entities
     *
     * @internal
     *
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>|TEntity
     * @phpstan-return (
     *     $operation is SyncOperation::*_LIST
     *     ? FluentIteratorInterface<array-key,TEntity>
     *     : TEntity
     * )
     */
    public function run(int $operation, ...$args);

    /**
     * Add an entity to the backend
     *
     * @param TEntity $entity
     * @param mixed ...$args
     * @return TEntity
     */
    public function create($entity, ...$args): SyncEntityInterface;

    /**
     * Get an entity from the backend
     *
     * @param int|string|null $id
     * @param mixed ...$args
     * @return TEntity
     */
    public function get($id, ...$args): SyncEntityInterface;

    /**
     * Update an entity in the backend
     *
     * @param TEntity $entity
     * @param mixed ...$args
     * @return TEntity
     */
    public function update($entity, ...$args): SyncEntityInterface;

    /**
     * Delete an entity from the backend
     *
     * @param TEntity $entity
     * @param mixed ...$args
     * @return TEntity
     */
    public function delete($entity, ...$args): SyncEntityInterface;

    /**
     * Add a list of entities to the backend
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function createList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Get a list of entities from the backend
     *
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function getList(...$args): FluentIteratorInterface;

    /**
     * Update a list of entities in the backend
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function updateList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Delete a list of entities from the backend
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function deleteList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Perform an arbitrary sync operation on a list of backend entities and
     * return an array
     *
     * @internal
     *
     * @param SyncOperation::*_LIST $operation
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function runA(int $operation, ...$args): array;

    /**
     * Add a list of entities to the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function createListA(iterable $entities, ...$args): array;

    /**
     * Get a list of entities from the backend as an array
     *
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function getListA(...$args): array;

    /**
     * Update a list of entities in the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function updateListA(iterable $entities, ...$args): array;

    /**
     * Delete a list of entities from the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @param mixed ...$args
     * @return array<TEntity>
     */
    public function deleteListA(iterable $entities, ...$args): array;

    /**
     * Use a property of the entity class to resolve names to entities
     *
     * @param (Closure(TEntity): string)|string|null $nameProperty If `null`,
     * entity names are taken from {@see SyncEntityInterface::getName()}.
     * @param int-mask-of<SyncEntityProviderInterface::*> $flags
     * @param array<SyncEntityProviderInterface::ALGORITHM_*,float>|float|null $uncertaintyThreshold If
     * the uncertainty of a match for a given name is greater than or equal to
     * this value (between `0.0` and `1.0`), the entity is not returned.
     * @param (Closure(TEntity): (int|float))|string|null $weightProperty If
     * multiple entities are equally similar to a given name, the one with the
     * greatest weight (highest value) is preferred.
     * @return SyncEntityResolverInterface<TEntity>
     */
    public function getResolver(
        $nameProperty = null,
        int $flags = SyncEntityProviderInterface::ALGORITHM_SAME,
        $uncertaintyThreshold = null,
        $weightProperty = null,
        bool $requireOneMatch = false
    ): SyncEntityResolverInterface;

    /**
     * Perform sync operations on the backend directly, ignoring any entities in
     * the entity store
     *
     * @return $this
     */
    public function online();

    /**
     * Perform "get" operations on the entity store, throwing an exception if
     * entities have never been synced with the backend
     *
     * @return $this
     */
    public function offline();

    /**
     * Retrieve entities directly from the backend unless it cannot be reached
     * or the entity store's copies are sufficiently fresh
     *
     * @return $this
     */
    public function offlineFirst();

    /**
     * Do not resolve deferred entities or relationships
     *
     * @return $this
     */
    public function doNotResolve();

    /**
     * Resolve deferred entities and relationships immediately
     *
     * @return $this
     */
    public function resolveEarly();

    /**
     * Resolve deferred entities and relationships after reaching the end of
     * each stream of entity data
     *
     * @return $this
     */
    public function resolveLate();

    /**
     * Do not hydrate entities returned by the backend
     *
     * @return $this
     */
    public function doNotHydrate();

    /**
     * Apply the given hydration policy to entities returned by the backend
     *
     * @param HydrationPolicy::* $policy
     * @param class-string<SyncEntityInterface>|null $entity
     * @param array<int<1,max>>|int<1,max>|null $depth
     * @return $this
     */
    public function hydrate(
        int $policy = HydrationPolicy::EAGER,
        ?string $entity = null,
        $depth = null
    );
}
