<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IIterable;
use Lkrms\Sync\Support\SyncOperation;

/**
 * Provides an entity-agnostic interface to an ISyncProvider's implementation of
 * sync operations for an entity
 *
 * @template TEntity of ISyncEntity
 * @template TList of array|IIterable
 */
interface ISyncEntityProvider
{
    /**
     * Perform an arbitrary sync operation on one or more backend entities
     *
     * @internal
     * @param int $operation A {@see SyncOperation} value.
     * @phpstan-param SyncOperation::* $operation
     */
    public function run(int $operation, ...$args);

    /**
     * Add an entity to the backend
     *
     * @param TEntity $entity
     * @return TEntity
     */
    public function create($entity, ...$args): ISyncEntity;

    /**
     * Get an entity from the backend
     *
     * @param int|string|null $id
     * @return TEntity
     */
    public function get($id, ...$args): ISyncEntity;

    /**
     * Update an entity in the backend
     *
     * @param TEntity $entity
     * @return TEntity
     */
    public function update($entity, ...$args): ISyncEntity;

    /**
     * Delete an entity from the backend
     *
     * @param TEntity $entity
     * @return TEntity
     */
    public function delete($entity, ...$args): ISyncEntity;

    /**
     * Add a list of entities to the backend
     *
     * @param iterable<TEntity> $entities
     * @return TList
     */
    public function createList(iterable $entities, ...$args);

    /**
     * Get a list of entities from the backend
     *
     * @return TList
     */
    public function getList(...$args);

    /**
     * Update a list of entities in the backend
     *
     * @param iterable<TEntity> $entities
     * @return TList
     */
    public function updateList(iterable $entities, ...$args);

    /**
     * Delete a list of entities from the backend
     *
     * @param iterable<TEntity> $entities
     * @return TList
     */
    public function deleteList(iterable $entities, ...$args);

    /**
     * Use a property of the entity class to resolve names to entities
     *
     */
    public function getResolver(string $nameProperty): ISyncEntityResolver;

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
}
