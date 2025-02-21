<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Contract\Sync\DeferralPolicy;
use Salient\Contract\Sync\HydrationPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncDefinitionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntityProviderInterface;
use Salient\Contract\Sync\SyncEntityResolverInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Iterator\IterableIterator;
use Salient\Sync\Exception\SyncOperationNotImplementedException;
use Salient\Sync\SyncUtil;
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
    /** @var class-string<TEntity> */
    private string $Entity;
    /** @var TProvider */
    private SyncProviderInterface $Provider;
    /** @var SyncDefinitionInterface<TEntity,TProvider> */
    private SyncDefinitionInterface $Definition;
    private SyncContextInterface $Context;
    private SyncStoreInterface $Store;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     */
    public function __construct(
        ContainerInterface $container,
        SyncProviderInterface $provider,
        string $entity,
        ?SyncContextInterface $context = null
    ) {
        if (!is_a($entity, SyncEntityInterface::class, true)) {
            throw new LogicException(sprintf(
                '%s does not implement %s',
                $entity,
                SyncEntityInterface::class,
            ));
        }

        if ($context && $context->getProvider() !== $provider) {
            throw new LogicException(sprintf(
                'Context has a different provider (%s, expected %s)',
                $context->getProvider()->getName(),
                $provider->getName(),
            ));
        }

        $entity = SyncUtil::getServicedEntityType($entity, $provider, $container);

        $this->Entity = $entity;
        $this->Provider = $provider;
        $this->Definition = $provider->getDefinition($entity);
        $this->Context = ($context ?? $provider->getContext())->withContainer($container);
        $this->Store = $provider->getStore();
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
    public function entity(): string
    {
        return $this->Entity;
    }

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     * @return iterable<array-key,TEntity>|TEntity
     * @phpstan-return (
     *     $operation is SyncOperation::*_LIST
     *     ? iterable<array-key,TEntity>
     *     : TEntity
     * )
     */
    private function _run(int $operation, ...$args)
    {
        $closure =
            $this
                ->Definition
                ->getOperationClosure($operation);

        if (!$closure) {
            throw new SyncOperationNotImplementedException(
                $this->Provider,
                $this->Entity,
                $operation
            );
        }

        return $closure(
            $this->Context->withOperation($operation, $this->Entity, ...$args),
            ...$args
        );
    }

    /**
     * @inheritDoc
     */
    public function run(int $operation, ...$args)
    {
        $fromCheckpoint = $this->Store->getDeferralCheckpoint();
        $deferralPolicy = $this->Context->getDeferralPolicy();

        if (!SyncUtil::isListOperation($operation)) {
            $result = $this->_run($operation, ...$args);

            if ($deferralPolicy === DeferralPolicy::RESOLVE_LATE) {
                $this->Store->resolveDeferrals($fromCheckpoint);
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

        if (!$result instanceof FluentIteratorInterface) {
            return new IterableIterator($result);
        }

        return $result;
    }

    /**
     * @param SyncOperation::*_LIST $operation
     * @param mixed ...$args
     * @return Generator<array-key,TEntity>
     */
    private function resolveDeferredEntitiesAfterRun(int $fromCheckpoint, int $operation, ...$args): Generator
    {
        yield from $this->_run($operation, ...$args);
        $this->Store->resolveDeferrals($fromCheckpoint);
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
            if ($id === null) {
                throw new LogicException('$id cannot be null when working offline');
            }
            $entity = $this->Store->registerEntityType($this->Entity)->getEntity(
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

    public function runA(int $operation, ...$args): array
    {
        // @phpstan-ignore staticMethod.alreadyNarrowedType
        if (!SyncUtil::isListOperation($operation)) {
            throw new LogicException('Not a *_LIST operation: ' . $operation);
        }

        $fromCheckpoint = $this->Store->getDeferralCheckpoint();
        $deferralPolicy = $this->Context->getDeferralPolicy();

        $result = $this->_run($operation, ...$args);
        if (!is_array($result)) {
            $result = iterator_to_array($result, false);
        }

        if ($deferralPolicy === DeferralPolicy::RESOLVE_LATE) {
            $this->Store->resolveDeferrals($fromCheckpoint);
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
        $this->Context = $this->Context->withOffline(false);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offline()
    {
        $this->Context = $this->Context->withOffline(true);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function offlineFirst()
    {
        $this->Context = $this->Context->withOffline(null);
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
        $nameProperty = null,
        int $flags = SyncEntityProvider::ALGORITHM_SAME,
        $uncertaintyThreshold = null,
        $weightProperty = null,
        bool $requireOneMatch = false
    ): SyncEntityResolverInterface {
        return is_string($nameProperty)
            && $flags === self::ALGORITHM_SAME
            && $weightProperty === null
            && !$requireOneMatch
                ? new SyncEntityResolver($this, $nameProperty)
                : new SyncEntityFuzzyResolver(
                    $this,
                    $nameProperty,
                    $flags,
                    $uncertaintyThreshold,
                    $weightProperty,
                    $requireOneMatch,
                );
    }
}
