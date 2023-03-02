<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Sync\Support\SyncOperation;

/**
 * Provides an entity-agnostic interface to an ISyncProvider's implementation of
 * sync operations for an entity
 *
 * @template TEntity of ISyncEntity
 */
interface ISyncEntityProvider
{
    /**
     * Perform an arbitrary sync operation on one or more backend entities
     *
     * @internal
     * @param int $operation A {@see SyncOperation} value.
     * @psalm-param SyncOperation::* $operation
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
     * @return iterable<TEntity>
     */
    public function createList(iterable $entities, ...$args): iterable;

    /**
     * Get a list of entities from the backend
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
     * Use a property of the entity class to resolve names to entities
     *
     */
    public function getResolver(string $nameProperty): ISyncEntityResolver;
}
