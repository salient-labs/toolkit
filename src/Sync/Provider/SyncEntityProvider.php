<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Lkrms\Contract\IContainer;
use Lkrms\Exception\SyncOperationNotImplementedException;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\SyncEntity;
use Lkrms\Sync\SyncOperation;
use UnexpectedValueException;

/**
 * Provides an entity-agnostic interface to a provider's implementation of sync
 * operations for an entity
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
class SyncEntityProvider
{
    /**
     * @var IContainer
     */
    private $Container;

    /**
     * @var string
     */
    private $Entity;

    /**
     * @var SyncProvider
     */
    private $Provider;

    /**
     * @var ISyncDefinition|null
     */
    private $Definition;

    /**
     * @var SyncContext
     */
    private $Context;

    public function __construct(IContainer $container, string $entity, SyncProvider $provider, ?ISyncDefinition $definition, ?SyncContext $context = null)
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

        $this->Container  = $container;
        $this->Entity     = $entity;
        $this->Provider   = $provider;
        $this->Definition = $definition;
        $this->Context    = $context ?: new SyncContext($container);
    }

    private function run(int $operation, ...$args)
    {
        if (!$this->Definition ||
            !($closure = $this->Definition->getSyncOperationClosure($operation)))
        {
            throw new SyncOperationNotImplementedException($this->Provider, $this->Entity, $operation);
        }

        return $closure($this->Context, ...$args);
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
     * public function createFaculty(Faculty $entity): Faculty;
     *
     * // 2.
     * public function create_Faculty(Faculty $entity): Faculty;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be the class of the entity
     *   being created
     * - MUST be required
     *
     * @param SyncEntity $entity
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return SyncEntity
     */
    public function create(SyncEntity $entity, ...$params): SyncEntity
    {
        return $this->run(SyncOperation::CREATE, $entity, ...$params);
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
     * public function getFaculty(int $id = null): Faculty;
     *
     * // 2.
     * public function get_Faculty(int $id = null): Faculty;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MAY have a type declaration, which MUST be one of `int`, `string`, or
     *   `int|string` if included
     * - MAY be nullable
     *
     * @param int|string|null $id
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return SyncEntity
     */
    public function get($id = null, ...$params): SyncEntity
    {
        return $this->run(SyncOperation::READ, $id, ...$params);
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
     * public function updateFaculty(Faculty $entity): Faculty;
     *
     * // 2.
     * public function update_Faculty(Faculty $entity): Faculty;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be the class of the entity
     *   being updated
     * - MUST be required
     *
     * @param SyncEntity $entity
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return SyncEntity
     */
    public function update(SyncEntity $entity, ...$params): SyncEntity
    {
        return $this->run(SyncOperation::UPDATE, $entity, ...$params);
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
     * public function deleteFaculty(Faculty $entity): Faculty;
     *
     * // 2.
     * public function delete_Faculty(Faculty $entity): Faculty;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be the class of the entity
     *   being deleted
     * - MUST be required
     *
     * The return value:
     * - SHOULD represent the final state of the entity before it was deleted
     * - MAY be `null`
     *
     * @param SyncEntity $entity
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return SyncEntity|null
     */
    public function delete(SyncEntity $entity, ...$params): ?SyncEntity
    {
        return $this->run(SyncOperation::DELETE, $entity, ...$params);
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
     * public function createFaculties(iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function createList_Faculty(iterable $entities): iterable;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be `array`
     * - MUST be required
     *
     * @param iterable<SyncEntity> $entities
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return iterable<SyncEntity>
     */
    public function createList(iterable $entities, ...$params): iterable
    {
        return $this->run(SyncOperation::CREATE_LIST, $entities, ...$params);
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
     * public function getFaculties(): iterable;
     *
     * // 2. With a singular name
     * public function getList_Faculty(): iterable;
     * ```
     *
     * @param mixed ...$params Parameters to pass to the provider.
     * @return iterable<SyncEntity>
     */
    public function getList(...$params): iterable
    {
        return $this->run(SyncOperation::READ_LIST, ...$params);
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
     * public function updateFaculties(iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function updateList_Faculty(iterable $entities): iterable;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be `array`
     * - MUST be required
     *
     * @param iterable<SyncEntity> $entities
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return iterable<SyncEntity>
     */
    public function updateList(iterable $entities, ...$params): iterable
    {
        return $this->run(SyncOperation::UPDATE_LIST, $entities, ...$params);
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
     * public function deleteFaculties(iterable $entities): ?iterable;
     *
     * // 2. With a singular name
     * public function deleteList_Faculty(iterable $entities): ?iterable;
     * ```
     *
     * The first parameter:
     * - MUST be defined
     * - MUST have a type declaration, which MUST be `array`
     * - MUST be required
     *
     * The return value:
     * - SHOULD represent the final state of the entities before they were
     *   deleted
     * - MAY be `null`
     *
     * @param iterable<SyncEntity> $entities
     * @param mixed ...$params Additional parameters to pass to the provider.
     * @return iterable<SyncEntity>|null
     */
    public function deleteList(iterable $entities, ...$params): ?iterable
    {
        return $this->run(SyncOperation::DELETE_LIST, $entities, ...$params);
    }

}
