<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Sync\Concept\SyncEntity;

/**
 * Provides an entity-agnostic interface to an ISyncProvider's implementation of
 * sync operations for an entity
 *
 * @template TEntity of SyncEntity
 */
interface ISyncEntityProvider
{
    /**
     * Add an entity to the backend
     *
     * @param TEntity $entity
     * @return TEntity
     */
    public function create(SyncEntity $entity, ...$args): SyncEntity;

    /**
     * Return an entity from the backend
     *
     * @param int|string|null $id
     * @return TEntity
     */
    public function get($id, ...$args): SyncEntity;

    /**
     * Update an entity in the backend
     *
     * @param TEntity $entity
     * @return TEntity
     */
    public function update(SyncEntity $entity, ...$args): SyncEntity;

    /**
     * Delete an entity from the backend
     *
     * @param TEntity $entity
     * @return TEntity
     */
    public function delete(SyncEntity $entity, ...$args): SyncEntity;

    /**
     * Add a list of entities to the backend
     *
     * @param iterable<TEntity> $entities
     * @return iterable<TEntity>
     */
    public function createList(iterable $entities, ...$args): iterable;

    /**
     * Return a list of entities from the backend
     *
     * @return iterable<TEntity>
     */
    public function getList(...$args): iterable;

    /**
     * Update a list of entities in the backend
     *
     * @param iterable<TEntity> $entities
     * @return iterable<TEntity>
     */
    public function updateList(iterable $entities, ...$args): iterable;

    /**
     * Delete a list of entities from the backend
     *
     * @param iterable<TEntity> $entities
     * @return iterable<TEntity>
     */
    public function deleteList(iterable $entities, ...$args): iterable;

    /**
     * Perform an arbitrary sync operation on one or more backend entities
     *
     * @internal
     * @param int $operation A {@see SyncOperation} value.
     * @psalm-param SyncOperation::* $operation
     */
    public function run(int $operation, ...$args);
}
