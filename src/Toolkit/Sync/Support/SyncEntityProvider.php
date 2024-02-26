<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\TextComparisonAlgorithm;
use Salient\Iterator\Contract\FluentIteratorInterface;
use Salient\Iterator\IterableIterator;
use Salient\Sync\Catalog\DeferralPolicy;
use Salient\Sync\Catalog\HydrationPolicy;
use Salient\Sync\Catalog\SyncOperation;
use Salient\Sync\Contract\SyncContextInterface;
use Salient\Sync\Contract\SyncDefinitionInterface;
use Salient\Sync\Contract\SyncEntityInterface;
use Salient\Sync\Contract\SyncEntityProviderInterface;
use Salient\Sync\Contract\SyncEntityResolverInterface;
use Salient\Sync\Contract\SyncProviderInterface;
use Salient\Sync\Exception\SyncOperationNotImplementedException;
use Salient\Sync\SyncStore;
use Generator;
use LogicException;

/**
 * An interface to a SyncProviderInterface's implementation of sync operations
 * for an SyncEntityInterface class
 *
 * So you can do this:
 *
 * ```php
 * <?php
 * $faculties = $provider->with(Faculty::class)->getList();
 * ```
 *
 * or, if a `Faculty` provider is bound to the current global container:
 *
 * ```php
 * <?php
 * $faculties = Faculty::withDefaultProvider()->getList();
 * ```
 *
 * @template TEntity of SyncEntityInterface
 * @template TProvider of SyncProviderInterface
 *
 * @implements SyncEntityProviderInterface<TEntity>
 */
final class SyncEntityProvider implements SyncEntityProviderInterface
{
    /**
     * @var class-string<TEntity>
     */
    private $Entity;

    /**
     * @todo Remove `SyncProviderInterface&` when Intelephense generics issues
     * are fixed
     *
     * @var SyncProviderInterface&TProvider
     */
    private $Provider;

    /**
     * @var SyncDefinitionInterface<TEntity,TProvider>
     */
    private $Definition;

    /**
     * @var SyncContextInterface
     */
    private $Context;

    /**
     * @var SyncStore
     */
    private $Store;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param SyncDefinitionInterface<TEntity,TProvider> $definition
     */
    public function __construct(
        ContainerInterface $container,
        string $entity,
        SyncProviderInterface $provider,
        SyncDefinitionInterface $definition,
        ?SyncContextInterface $context = null
    ) {
        if (!is_a($entity, SyncEntityInterface::class, true)) {
            throw new LogicException(sprintf(
                'Does not implement %s: %s',
                SyncEntityInterface::class,
                $entity,
            ));
        }

        $entityProvider = SyncIntrospector::entityToProvider($entity);
        if (!($provider instanceof $entityProvider)) {
            throw new LogicException(get_class($provider) . ' does not implement ' . $entityProvider);
        }

        $this->Entity = $entity;
        $this->Provider = $provider;
        $this->Definition = $definition;
        $this->Context = $context ?? $provider->getContext($container);
        $this->Store = $provider->store();
    }

    /**
     * @inheritDoc
     */
    public function getProvider(): SyncProviderInterface
    {
        return $this->Provider;
    }

    /**
     * @inheritDoc
     */
    public function requireProvider(): SyncProviderInterface
    {
        return $this->Provider;
    }

    /**
     * @inheritDoc
     */
    public function entity(): string
    {
        return $this->Entity;
    }

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return iterable<TEntity>|TEntity
     * @phpstan-return (
     *     $operation is SyncOperation::*_LIST
     *     ? iterable<TEntity>
     *     : TEntity
     * )
     */
    private function _run($operation, ...$args)
    {
        $closure =
            $this
                ->Definition
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
     * @inheritDoc
     */
    public function run($operation, ...$args)
    {
        $fromCheckpoint = $this->Store->getDeferralCheckpoint();
        $deferralPolicy = $this->Context->getDeferralPolicy();

        if (!SyncOperation::isList($operation)) {
            $result = $this->_run($operation, ...$args);

            if ($deferralPolicy === DeferralPolicy::RESOLVE_LATE) {
                $this->Store->resolveDeferred($fromCheckpoint);
            }

            return $result;
        }

        switch ($deferralPolicy) {
            case DeferralPolicy::DO_NOT_RESOLVE:
            case DeferralPolicy::RESOLVE_EARLY:
                $result = $this->_run($operation, ...$args);
                break;

            case DeferralPolicy::RESOLVE_LATE:
                $result = $this->resolveDeferredEntitiesAfterRun(
                    $fromCheckpoint,
                    $operation,
                    ...$args,
                );
                break;

            default:
                throw new LogicException(sprintf(
                    'Invalid deferral policy: %d',
                    $deferralPolicy,
                ));
        }

        if (!($result instanceof FluentIteratorInterface)) {
            return new IterableIterator($result);
        }

        return $result;
    }

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return Generator<TEntity>
     */
    private function resolveDeferredEntitiesAfterRun(int $fromCheckpoint, $operation, ...$args): Generator
    {
        yield from $this->_run($operation, ...$args);
        $this->Store->resolveDeferred($fromCheckpoint);
    }

    /**
     * Add an entity to the backend
     *
     * The underlying {@see SyncProviderInterface} must implement the
     * {@see SyncOperation::CREATE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1.
     * public function createFaculty(SyncContextInterface $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function create_Faculty(SyncContextInterface $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `SyncContextInterface $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being created
     * - must be required
     */
    public function create($entity, ...$args): SyncEntityInterface
    {
        return $this->run(SyncOperation::CREATE, $entity, ...$args);
    }

    /**
     * Get an entity from the backend
     *
     * The underlying {@see SyncProviderInterface} must implement the
     * {@see SyncOperation::READ} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1.
     * public function getFaculty(SyncContextInterface $ctx, $id): Faculty;
     *
     * // 2.
     * public function get_Faculty(SyncContextInterface $ctx, $id): Faculty;
     * ```
     *
     * The first parameter after `SyncContextInterface $ctx`:
     * - must be defined
     * - must not have a native type declaration, but may be tagged as an
     *   `int|string|null` parameter for static analysis purposes
     * - must be nullable
     *
     * @param int|string|null $id
     */
    public function get($id, ...$args): SyncEntityInterface
    {
        $offline = $this->Context->getOffline();
        if ($offline !== false) {
            $entity = $this->Store->entityType($this->Entity)->getEntity(
                $this->Provider->getProviderId(),
                $this->Entity,
                $id,
                $offline,
            );
            if ($entity) {
                return $entity;
            }
        }

        return $this->run(SyncOperation::READ, $id, ...$args);
    }

    /**
     * Update an entity in the backend
     *
     * The underlying {@see SyncProviderInterface} must implement the
     * {@see SyncOperation::UPDATE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1.
     * public function updateFaculty(SyncContextInterface $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function update_Faculty(SyncContextInterface $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `SyncContextInterface $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being updated
     * - must be required
     */
    public function update($entity, ...$args): SyncEntityInterface
    {
        return $this->run(SyncOperation::UPDATE, $entity, ...$args);
    }

    /**
     * Delete an entity from the backend
     *
     * The underlying {@see SyncProviderInterface} must implement the
     * {@see SyncOperation::DELETE} operation, e.g. one of the following for a
     * `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1.
     * public function deleteFaculty(SyncContextInterface $ctx, Faculty $entity): Faculty;
     *
     * // 2.
     * public function delete_Faculty(SyncContextInterface $ctx, Faculty $entity): Faculty;
     * ```
     *
     * The first parameter after `SyncContextInterface $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be the class of the
     *   entity being deleted
     * - must be required
     *
     * The return value:
     * - must represent the final state of the entity before it was deleted
     */
    public function delete($entity, ...$args): SyncEntityInterface
    {
        return $this->run(SyncOperation::DELETE, $entity, ...$args);
    }

    /**
     * Add a list of entities to the backend
     *
     * The underlying {@see SyncProviderInterface} must implement the
     * {@see SyncOperation::CREATE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1. With a plural entity name
     * public function createFaculties(SyncContextInterface $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function createList_Faculty(SyncContextInterface $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `SyncContextInterface $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function createList(iterable $entities, ...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::CREATE_LIST, $entities, ...$args);
    }

    /**
     * Get a list of entities from the backend
     *
     * The underlying {@see SyncProviderInterface} must implement the
     * {@see SyncOperation::READ_LIST} operation, e.g. one of the following for
     * a `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1. With a plural entity name
     * public function getFaculties(SyncContextInterface $ctx): iterable;
     *
     * // 2. With a singular name
     * public function getList_Faculty(SyncContextInterface $ctx): iterable;
     * ```
     *
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function getList(...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::READ_LIST, ...$args);
    }

    /**
     * Update a list of entities in the backend
     *
     * The underlying {@see SyncProviderInterface} must implement the
     * {@see SyncOperation::UPDATE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1. With a plural entity name
     * public function updateFaculties(SyncContextInterface $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function updateList_Faculty(SyncContextInterface $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `SyncContextInterface $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function updateList(iterable $entities, ...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::UPDATE_LIST, $entities, ...$args);
    }

    /**
     * Delete a list of entities from the backend
     *
     * The underlying {@see SyncProviderInterface} must implement the
     * {@see SyncOperation::DELETE_LIST} operation, e.g. one of the following
     * for a `Faculty` entity:
     *
     * ```php
     * <?php
     * // 1. With a plural entity name
     * public function deleteFaculties(SyncContextInterface $ctx, iterable $entities): iterable;
     *
     * // 2. With a singular name
     * public function deleteList_Faculty(SyncContextInterface $ctx, iterable $entities): iterable;
     * ```
     *
     * The first parameter after `SyncContextInterface $ctx`:
     * - must be defined
     * - must have a native type declaration, which must be `iterable`
     * - must be required
     *
     * The return value:
     * - must represent the final state of the entities before they were deleted
     *
     * @param iterable<TEntity> $entities
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    public function deleteList(iterable $entities, ...$args): FluentIteratorInterface
    {
        return $this->run(SyncOperation::DELETE_LIST, $entities, ...$args);
    }

    public function runA($operation, ...$args): array
    {
        if (!SyncOperation::isList($operation)) {
            throw new LogicException('Not a *_LIST operation: ' . $operation);
        }

        $fromCheckpoint = $this->Store->getDeferralCheckpoint();
        $deferralPolicy = $this->Context->getDeferralPolicy();

        $result = $this->_run($operation, ...$args);
        if (!is_array($result)) {
            $result = iterator_to_array($result);
        }

        if ($deferralPolicy === DeferralPolicy::RESOLVE_LATE) {
            $this->Store->resolveDeferred($fromCheckpoint);
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

    /**
     * @inheritDoc
     */
    public function online()
    {
        $this->Context = $this->Context->online();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offline()
    {
        $this->Context = $this->Context->offline();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offlineFirst()
    {
        $this->Context = $this->Context->offlineFirst();
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function doNotResolve()
    {
        $this->Context = $this->Context->withDeferralPolicy(
            DeferralPolicy::DO_NOT_RESOLVE,
        );
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolveEarly()
    {
        $this->Context = $this->Context->withDeferralPolicy(
            DeferralPolicy::RESOLVE_EARLY,
        );
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolveLate()
    {
        $this->Context = $this->Context->withDeferralPolicy(
            DeferralPolicy::RESOLVE_LATE,
        );
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function doNotHydrate()
    {
        $this->Context = $this->Context->withHydrationPolicy(
            HydrationPolicy::SUPPRESS,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function hydrate(
        int $policy = HydrationPolicy::EAGER,
        ?string $entity = null,
        $depth = null
    ) {
        $this->Context = $this->Context->withHydrationPolicy(
            $policy,
            $entity,
            $depth,
        );
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getResolver(
        ?string $nameProperty = null,
        int $algorithm = TextComparisonAlgorithm::SAME,
        $uncertaintyThreshold = null,
        ?string $weightProperty = null,
        bool $requireOneMatch = false
    ): SyncEntityResolverInterface {
        if ($nameProperty !== null &&
                $algorithm === TextComparisonAlgorithm::SAME &&
                $weightProperty === null &&
                !$requireOneMatch) {
            return new SyncEntityResolver($this, $nameProperty);
        }
        return new SyncEntityFuzzyResolver(
            $this,
            $nameProperty,
            $algorithm,
            $uncertaintyThreshold,
            $weightProperty,
            $requireOneMatch,
        );
    }
}
