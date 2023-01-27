<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Contract\IContainer;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncEntityProvider;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Exception\SyncOperationNotImplementedException;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncOperation;
use UnexpectedValueException;

/**
 * Provides an entity-agnostic interface to an ISyncProvider's implementation of
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
 * @template TEntity of ISyncEntity
 * @implements ISyncEntityProvider<TEntity>
 */
final class SyncEntityProvider implements ISyncEntityProvider
{
    /**
     * @var string
     */
    private $Entity;

    /**
     * @var ISyncProvider
     */
    private $Provider;

    /**
     * @var ISyncDefinition
     */
    private $Definition;

    /**
     * @var ISyncContext
     */
    private $Context;

    public function __construct(IContainer $container, string $entity, ISyncProvider $provider, ISyncDefinition $definition, ?ISyncContext $context = null)
    {
        if (!is_subclass_of($entity, SyncEntity::class)) {
            throw new UnexpectedValueException("Not a subclass of SyncEntity: $entity");
        }

        $entityProvider = SyncIntrospector::entityToProvider($entity);
        if (!($provider instanceof $entityProvider)) {
            throw new UnexpectedValueException(get_class($provider) . ' does not implement ' . $entityProvider);
        }

        $this->Entity     = $entity;
        $this->Provider   = $provider;
        $this->Definition = $definition;
        $this->Context    = $context ?: $container->get(SyncContext::class);
    }

    public function run(int $operation, ...$args)
    {
        if (!($closure = $this->Definition->getSyncOperationClosure($operation))) {
            throw new SyncOperationNotImplementedException($this->Provider, $this->Entity, $operation);
        }

        $result = $closure($this->Context->withArgs($operation, $this->Context, ...$args), ...$args);

        if (SyncOperation::isList($operation) && $this->Context->getListToArray() && !is_array($result)) {
            $entities = [];
            foreach ($result as $entity) {
                $entities[] = $entity;
            }

            return $entities;
        }

        return $result;
    }

    /**
     * Defer retrieval of an entity from the backend
     *
     * @param int|string $id
     * @param mixed $replace A reference to the variable, property or array
     * element to replace when the entity is resolved. Do not assign anything
     * else to it after calling this method.
     */
    public function defer($id, &$replace): void
    {
        DeferredSyncEntity::defer($this->Provider, $this->Context, $this->Entity, $id, $replace);
    }

    /**
     * Defer retrieval of a list of entities from the backend
     *
     * @param int[]|string[] $idList
     * @param mixed $replace A reference to the variable, property or array
     * element to replace when the list is resolved. Do not assign anything else
     * to it after calling this method.
     */
    public function deferList(array $idList, &$replace): void
    {
        DeferredSyncEntity::deferList($this->Provider, $this->Context, $this->Entity, $idList, $replace);
    }

    /**
     * Add an entity to the backend
     *
     * The underlying {@see ISyncProvider} must implement the
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
    public function create($entity, ...$args): ISyncEntity
    {
        return $this->run(SyncOperation::CREATE, $entity, ...$args);
    }

    /**
     * Return an entity from the backend
     *
     * The underlying {@see ISyncProvider} must implement the
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
    public function get($id, ...$args): ISyncEntity
    {
        return $this->run(SyncOperation::READ, $id, ...$args);
    }

    /**
     * Update an entity in the backend
     *
     * The underlying {@see ISyncProvider} must implement the
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
    public function update($entity, ...$args): ISyncEntity
    {
        return $this->run(SyncOperation::UPDATE, $entity, ...$args);
    }

    /**
     * Delete an entity from the backend
     *
     * The underlying {@see ISyncProvider} must implement the
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
    public function delete($entity, ...$args): ISyncEntity
    {
        return $this->run(SyncOperation::DELETE, $entity, ...$args);
    }

    /**
     * Add a list of entities to the backend
     *
     * The underlying {@see ISyncProvider} must implement the
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
     * The underlying {@see ISyncProvider} must implement the
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
     * The underlying {@see ISyncProvider} must implement the
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
     * The underlying {@see ISyncProvider} must implement the
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

    public function getResolver(string $nameProperty): SyncEntityResolver
    {
        return new SyncEntityResolver($this, $nameProperty);
    }

    /**
     * @param string|null $weightProperty If multiple entities are equally
     * similar to a given name, the one with the highest weight is preferred.
     * @param int|null $algorithm Overrides the default string comparison
     * algorithm. Either {@see SyncEntityFuzzyResolver::ALGORITHM_LEVENSHTEIN}
     * or {@see SyncEntityFuzzyResolver::ALGORITHM_SIMILAR_TEXT}.
     */
    public function getFuzzyResolver(string $nameProperty, ?string $weightProperty, ?int $algorithm = null, ?float $uncertaintyThreshold = null): SyncEntityFuzzyResolver
    {
        return new SyncEntityFuzzyResolver($this, $nameProperty, $weightProperty, $algorithm, $uncertaintyThreshold);
    }
}
