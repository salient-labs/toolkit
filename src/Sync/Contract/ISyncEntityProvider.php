<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Sync\Catalog\SyncOperation;

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
     * @phpstan-param SyncOperation::* $operation
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
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function createList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Get a list of entities from the backend
     *
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function getList(...$args): FluentIteratorInterface;

    /**
     * Update a list of entities in the backend
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function updateList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Delete a list of entities from the backend
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function deleteList(iterable $entities, ...$args): FluentIteratorInterface;

    /**
     * Perform an arbitrary sync operation on a list of backend entities and
     * return an array
     *
     * @internal
     * @param int $operation One of the {@see SyncOperation}::*_LIST values.
     * @phpstan-param SyncOperation::*_LIST $operation
     * @return array<TEntity>
     */
    public function runA(int $operation, ...$args): array;

    /**
     * Add a list of entities to the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @return array<TEntity>
     */
    public function createListA(iterable $entities, ...$args): array;

    /**
     * Get a list of entities from the backend as an array
     *
     * @return array<TEntity>
     */
    public function getListA(...$args): array;

    /**
     * Update a list of entities in the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @return array<TEntity>
     */
    public function updateListA(iterable $entities, ...$args): array;

    /**
     * Delete a list of entities from the backend and return an array
     *
     * @param iterable<TEntity> $entities
     * @return array<TEntity>
     */
    public function deleteListA(iterable $entities, ...$args): array;

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
