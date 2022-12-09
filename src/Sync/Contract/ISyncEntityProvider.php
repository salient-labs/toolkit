<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Sync\Concept\SyncEntity;

/**
 * Provides an entity-agnostic interface to an ISyncProvider's implementation of
 * sync operations for an entity
 *
 */
interface ISyncEntityProvider
{
    /**
     * Add an entity to the backend
     *
     */
    public function create(SyncEntity $entity, ...$args): SyncEntity;

    /**
     * Return an entity from the backend
     *
     * @param int|string|null $id
     */
    public function get($id, ...$args): SyncEntity;

    /**
     * Update an entity in the backend
     *
     */
    public function update(SyncEntity $entity, ...$args): SyncEntity;

    /**
     * Delete an entity from the backend
     *
     */
    public function delete(SyncEntity $entity, ...$args): SyncEntity;

    /**
     * Add a list of entities to the backend
     *
     * @param iterable<SyncEntity> $entities
     * @return iterable<SyncEntity>
     */
    public function createList(iterable $entities, ...$args): iterable;

    /**
     * Return a list of entities from the backend
     *
     * @return iterable<SyncEntity>
     */
    public function getList(...$args): iterable;

    /**
     * Update a list of entities in the backend
     *
     * @param iterable<SyncEntity> $entities
     * @return iterable<SyncEntity>
     */
    public function updateList(iterable $entities, ...$args): iterable;

    /**
     * Delete a list of entities from the backend
     *
     * @param iterable<SyncEntity> $entities
     * @return iterable<SyncEntity>
     */
    public function deleteList(iterable $entities, ...$args): iterable;
}
