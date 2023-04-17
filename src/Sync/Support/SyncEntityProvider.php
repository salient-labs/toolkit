<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Contract\IContainer;
use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Support\Iterator\IterableIterator;
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
 * An interface to an ISyncProvider's implementation of sync operations for an
 * ISyncEntity class
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
 * $faculties = Faculty::withDefaultProvider()->getList();
 * ```
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of ISyncProvider
 * @implements ISyncEntityProvider<TEntity>
 */
final class SyncEntityProvider implements ISyncEntityProvider
{
    /**
     * @var class-string<TEntity>
     */
    private $Entity;

    /**
     * @var TProvider
     */
    private $Provider;

    /**
     * @var ISyncDefinition<TEntity,TProvider>
     */
    private $Definition;

    /**
     * @var ISyncContext
     */
    private $Context;

    /**
     * @var bool|null
     */
    private $GetFromSyncStore;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param ISyncDefinition<TEntity,TProvider> $definition
     * @param ISyncContext|null $context
     */
    public function __construct(
        IContainer $container,
        string $entity,
        ISyncProvider $provider,
        ISyncDefinition $definition,
        ?ISyncContext $context = null
    ) {
        if (!is_a($entity, ISyncEntity::class, true)) {
            throw new UnexpectedValueException("Does not implement ISyncEntity: $entity");
        }

        $entityProvider = SyncIntrospector::entityToProvider($entity);
        if (!($provider instanceof $entityProvider)) {
            throw new UnexpectedValueException(get_class($provider) . ' does not implement ' . $entityProvider);
        }

        $this->Entity = $entity;
        $this->Provider = $provider;
        $this->Definition = $definition;
        $this->Context = $context ?: $container->get(SyncContext::class);
    }

    /**
     * @phpstan-param SyncOperation::* $operation
     * @return iterable<TEntity>|TEntity
     * @phpstan-return (
     *     $operation is SyncOperation::*_LIST
     *     ? iterable<TEntity>
     *     : TEntity
     * )
     */
    private function _run(int $operation, ...$args)
    {
        $closure = $this->Definition
                        ->getSyncOperationClosure($operation);

        if (!$closure) {
            throw new SyncOperationNotImplementedException(
                $this->Provider,
                $this->Entity,
                $operation
            );
        }

        return $closure(
            $this->Context->withArgs($operation, ...$args),
            ...$args
        );
    }

    /**
     * @internal
     */
    public function run(int $operation, ...$args)
    {
        if (!SyncOperation::isList($operation)) {
            return $this->_run($operation, ...$args);
        }

        $result = $this->_run($operation, ...$args);
        if (!($result instanceof FluentIteratorInterface)) {
            return new IterableIterator($result);
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
     * public function createFaculty(ISyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function create_Faculty(ISyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
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
     * Get an entity from the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::READ} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * // 1.
     * public function getFaculty(ISyncContext $ctx, $id): Faculty;
     *
     * // 2.
     * public function get_Faculty(ISyncContext $ctx, $id): Faculty;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
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
     * public function updateFaculty(ISyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function update_Faculty(ISyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
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
     * public function deleteFaculty(ISyncContext $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function delete_Faculty(ISyncContext $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
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
     * public function createFaculties(ISyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function createList_Faculty(ISyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<int|string,TEntity>
     */
    public function createList(iterable $entities, ...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::CREATE_LIST, $entities, ...$args);
    }

    /**
     * Get a list of entities from the backend
     *
     * The underlying {@see ISyncProvider} must implement the
     * {@see SyncOperation::READ_LIST} operation, e.g. one of the following for
     * a `Faculty` entity:
     *
     * ```php
     * // 1. With a plural entity name
     * public function getFaculties(ISyncContext $ctx): iterable;
     *
     * // 2. With a singular name
     * public function getList_Faculty(ISyncContext $ctx): iterable;
     * ```
     *
     * @return FluentIteratorInterface<int|string,TEntity>
     */
    public function getList(...$args): FluentIteratorInterface
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
     * public function updateFaculties(ISyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function updateList_Faculty(ISyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<int|string,TEntity>
     */
    public function updateList(iterable $entities, ...$args): FluentIteratorInterface
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
     * public function deleteFaculties(ISyncContext $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function deleteList_Faculty(ISyncContext $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `ISyncContext $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * The return value:
     * - must represent the final state of the entities before they were deleted
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<int|string,TEntity>
     */
    public function deleteList(iterable $entities, ...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::DELETE_LIST, $entities, ...$args);
    }

    public function runA(int $operation, ...$args): array
    {
        if (!SyncOperation::isList($operation)) {
            throw new UnexpectedValueException('Not a *_LIST operation: ' . $operation);
        }

        $result = $this->_run($operation, ...$args);
        if (!is_array($result)) {
            return iterator_to_array($result);
        }

        return $result;
    }

    public function createListA(iterable $entities, ...$args): array
    {
        return $this->runA(SyncOperation::CREATE_LIST, $entities, ...$args);
    }

    public function getListA(...$args): array
    {
        return $this->runA(SyncOperation::READ_LIST, ...$args);
    }

    public function updateListA(iterable $entities, ...$args): array
    {
        return $this->runA(SyncOperation::UPDATE_LIST, $entities, ...$args);
    }

    public function deleteListA(iterable $entities, ...$args): array
    {
        return $this->runA(SyncOperation::DELETE_LIST, $entities, ...$args);
    }

    public function online()
    {
        $this->GetFromSyncStore = false;

        return $this;
    }

    public function offline()
    {
        $this->GetFromSyncStore = true;

        return $this;
    }

    /**
     * Use a property of the entity class to resolve names to entities
     *
     */
    public function getResolver(string $nameProperty): SyncEntityResolver
    {
        return new SyncEntityResolver($this, $nameProperty);
    }

    /**
     * Use a property of the entity class to resolve names to entities using a
     * text similarity algorithm
     *
     * @param string|null $weightProperty If multiple entities are equally
     * similar to a given name, the one with the highest weight is preferred.
     * @param int|null $algorithm Overrides the default string comparison
     * algorithm. Either {@see SyncEntityFuzzyResolver::ALGORITHM_LEVENSHTEIN}
     * or {@see SyncEntityFuzzyResolver::ALGORITHM_SIMILAR_TEXT}.
     */
    public function getFuzzyResolver(
        string $nameProperty,
        ?string $weightProperty,
        ?int $algorithm = null,
        ?float $uncertaintyThreshold = null
    ): SyncEntityFuzzyResolver {
        return new SyncEntityFuzzyResolver($this, $nameProperty, $weightProperty, $algorithm, $uncertaintyThreshold);
    }
}
