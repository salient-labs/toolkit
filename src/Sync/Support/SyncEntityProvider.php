<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Contract\IContainer;
use Lkrms\Exception\SyncOperationNotImplementedException;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncOperation;
use UnexpectedValueException;

/**
 * Provides an entity-agnostic interface to a SyncProvider's implementation of
 * sync operations for an entity
 *
 * So you can do this:
 *
 * ```php
 * $faculties = $provider->with(Faculty::class)->getList();
 * ```
 *
 * or, if a `Faculty` provider is bound to the current global container:
 *
 * ```php
 * $faculties = Faculty::backend()->getList();
 * ```
 *
 */
class SyncEntityProvider implements ISyncEntityProvider
{
    /**
     * @var string
     */
    private $Entity;

    /**
     * @var SyncProvider
     */
    private $Provider;

    /**
     * @var ISyncDefinition
     */
    private $Definition;

    /**
     * @var SyncContext
     */
    private $Context;

    public function __construct(IContainer $container, string $entity, SyncProvider $provider, ISyncDefinition $definition, ?SyncContext $context = null)
    {
        if (!is_subclass_of($entity, SyncEntity::class))
        {
            throw new UnexpectedValueException("Not a subclass of SyncEntity: " . $entity);
        }

        $entityProvider = $entity . "Provider";
        if (!($provider instanceof $entityProvider))
        {
            throw new UnexpectedValueException(get_class($provider) . " does not implement " . $entityProvider);
        }

        $this->Entity     = $entity;
        $this->Provider   = $provider;
        $this->Definition = $definition;
        $this->Context    = $context ?: new SyncContext($container);
    }

    private function run(int $operation, ...$args)
    {
        if (!($closure = $this->Definition->getSyncOperationClosure($operation)))
        {
            throw new SyncOperationNotImplementedException($this->Provider, $this->Entity, $operation);
        }

        return $closure($this->Context->withArgs(...$args), ...$args);
    }

    /**
     * Add an entity to the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::CREATE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function createFaculty(SyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function create_Faculty(SyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `SyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being created
     * - must be required
     */
    public function create(SyncEntity $entity, ...$args): SyncEntity
    {
        return $this->run(SyncOperation::CREATE, $entity, ...$args);
    }

    /**
     * Return an entity from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::READ} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function getFaculty(SyncContext $ctx, $id): Faculty;
     *
     * // 2.
     * public function get_Faculty(SyncContext $ctx, $id): Faculty;
     * ```
     *
     * The first parameter after `SyncContext $ctx`:
     * - must be defined
     * - must not have a native type declaration, but may be tagged as an
     *   `int|string|null` parameter for static analysis purposes
     * - must be nullable
     *
     * @param int|string|null $id
     */
    public function get($id, ...$args): SyncEntity
    {
        return $this->run(SyncOperation::READ, $id, ...$args);
    }

    /**
     * Update an entity in the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::UPDATE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function updateFaculty(SyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function update_Faculty(SyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `SyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being updated
     * - must be required
     */
    public function update(SyncEntity $entity, ...$args): SyncEntity
    {
        return $this->run(SyncOperation::UPDATE, $entity, ...$args);
    }

    /**
     * Delete an entity from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::DELETE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function deleteFaculty(SyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function delete_Faculty(SyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `SyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being deleted
     * - must be required
     *
     * The return value:
     * - must represent the final state of the entity before it was deleted
     */
    public function delete(SyncEntity $entity, ...$args): SyncEntity
    {
        return $this->run(SyncOperation::DELETE, $entity, ...$args);
    }

    /**
     * Add a list of entities to the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::CREATE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function createFaculties(SyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function createList_Faculty(SyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `SyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * @param iterable<SyncEntity> $entities
     * @return iterable<SyncEntity>
     */
    public function createList(iterable $entities, ...$args): iterable
    {
        return $this->run(SyncOperation::CREATE_LIST, $entities, ...$args);
    }

    /**
     * Return a list of entities from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::READ_LIST} operation, e.g. one of the following for
     * a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function getFaculties(SyncContext $ctx): iterable;
     *
     * // 2. With a singular name
     * public function getList_Faculty(SyncContext $ctx): iterable;
     * ```
     *
     * @return iterable<SyncEntity>
     */
    public function getList(...$args): iterable
    {
        return $this->run(SyncOperation::READ_LIST, ...$args);
    }

    /**
     * Update a list of entities in the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::UPDATE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function updateFaculties(SyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function updateList_Faculty(SyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `SyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * @param iterable<SyncEntity> $entities
     * @return iterable<SyncEntity>
     */
    public function updateList(iterable $entities, ...$args): iterable
    {
        return $this->run(SyncOperation::UPDATE_LIST, $entities, ...$args);
    }

    /**
     * Delete a list of entities from the backend
     *
     * The underlying {@see SyncProvider} must implement the
     * {@see SyncOperation::DELETE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function deleteFaculties(SyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function deleteList_Faculty(SyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `SyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * The return value:
     * - must represent the final state of the entities before they were deleted
     *
     * @param iterable<SyncEntity> $entities
     * @return iterable<SyncEntity>
     */
    public function deleteList(iterable $entities, ...$args): iterable
    {
        return $this->run(SyncOperation::DELETE_LIST, $entities, ...$args);
    }

}
