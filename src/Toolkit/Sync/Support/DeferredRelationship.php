<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Utility\Get;
use ArrayIterator;
use IteratorAggregate;
use LogicException;
use Traversable;

/**
 * The promise of a sync entity relationship that hasn't been retrieved yet
 *
 * @template TEntity of SyncEntityInterface
 *
 * @implements IteratorAggregate<array-key,TEntity>
 */
final class DeferredRelationship implements IteratorAggregate
{
    /**
     * The provider servicing the entity
     *
     * @var SyncProviderInterface
     */
    private $Provider;

    /**
     * The context within which the provider is servicing the entity
     *
     * @var SyncContextInterface|null
     */
    private $Context;

    /**
     * The entity to instantiate
     *
     * @var class-string<TEntity>
     */
    private $Entity;

    /**
     * The entity for which the relationship is deferred
     *
     * @var class-string<SyncEntityInterface>
     */
    private $ForEntity;

    /**
     * The entity property for which the relationship is deferred
     *
     * @var string
     * @phpstan-ignore-next-line
     */
    private $ForEntityProperty;

    /**
     * The identifier of the entity for which the relationship is deferred
     *
     * @var int|string
     */
    private $ForEntityId;

    /**
     * Overrides the default filter passed to the provider when requesting
     * entities
     *
     * @var array<string,mixed>|null
     */
    private $Filter;

    /** @var array<TEntity>|DeferredRelationship<TEntity>|null */
    private $Replace;
    /** @var (callable(TEntity[]): void)|null */
    private $Callback;
    /** @var array<TEntity>|null */
    private $Resolved;

    /**
     * Creates a new DeferredRelationship object
     *
     * @param class-string<TEntity> $entity
     * @param class-string<SyncEntityInterface> $forEntity
     * @param int|string $forEntityId
     * @param array<string,mixed>|null $filter
     * @param array<TEntity>|DeferredRelationship<TEntity>|null $replace
     * @param (callable(TEntity[]): void)|null $callback
     */
    private function __construct(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        string $forEntity,
        string $forEntityProperty,
        $forEntityId,
        ?array $filter,
        &$replace,
        ?callable $callback = null
    ) {
        $this->Provider = $provider;
        $this->Context = $context;
        $this->Entity = $entity;
        $this->ForEntity = $forEntity;
        $this->ForEntityProperty = $forEntityProperty;
        $this->ForEntityId = $forEntityId;
        $this->Filter = $filter;

        if ($callback) {
            $this->Callback = $callback;
        } else {
            $this->Replace = &$replace;
            $this->Replace = $this;
        }

        $this
            ->store()
            ->registerEntity($entity)
            ->registerEntity($forEntity)
            ->deferRelationship(
                $this->Provider->getProviderId(),
                $entity,
                $forEntity,
                $forEntityProperty,
                $forEntityId,
                $this,
            );
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->resolve());
    }

    /**
     * Resolve the deferred relationship with entities retrieved from the
     * provider or the local entity store
     *
     * @return TEntity[]
     */
    public function resolve(): array
    {
        if ($this->Resolved !== null) {
            return $this->Resolved;
        }

        $entities =
            $this
                ->Provider
                ->with($this->Entity, $this->Context)
                ->getListA(
                    $this->Filter !== null
                        ? $this->Filter
                        : [
                            Get::basename($this->ForEntity) =>
                                $this->ForEntityId,
                        ],
                );

        $this->apply($entities);
        return $entities;
    }

    /**
     * Use a list of entities to resolve the deferred relationship
     *
     * @param TEntity[] $entities
     */
    public function replace(array $entities): void
    {
        if ($this->Resolved !== null) {
            throw new LogicException('Relationship already resolved');
        }

        $this->apply($entities);
    }

    /**
     * @param TEntity[] $entities
     */
    private function apply(array $entities): void
    {
        $this->Resolved = $entities;

        if ($this->Callback) {
            ($this->Callback)($entities);
            return;
        }

        $this->Replace = $entities;
        unset($this->Replace);
    }

    /**
     * Defer retrieval of a sync entity relationship
     *
     * @param SyncProviderInterface $provider The provider servicing the entity.
     * @param SyncContextInterface|null $context The context within which the provider
     * is servicing the entity.
     * @param class-string<TEntity> $entity The entity to instantiate.
     * @param class-string<SyncEntityInterface> $forEntity The entity for which the
     * relationship is deferred.
     * @param string $forEntityProperty The entity property for which the
     * relationship is deferred.
     * @param int|string $forEntityId The identifier of the entity for which the
     * relationship is deferred.
     * @param array<string,mixed>|null $filter Overrides the default filter
     * passed to the provider when requesting entities.
     * @param array<TEntity>|DeferredRelationship<TEntity>|null $replace Refers
     * to the variable or property to replace when the relationship is resolved.
     * Do not assign anything else to it after calling this method.
     * @param (callable(TEntity[]): void)|null $callback If given, `$replace` is
     * ignored and the resolved relationship is passed to the callback.
     */
    public static function defer(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        string $forEntity,
        string $forEntityProperty,
        $forEntityId,
        ?array $filter = null,
        &$replace = null,
        ?callable $callback = null
    ): void {
        new self(
            $provider,
            $context,
            $entity,
            $forEntity,
            $forEntityProperty,
            $forEntityId,
            $filter,
            $replace,
            $callback,
        );
    }

    /**
     * Get the context within which the provider is servicing the entity
     */
    public function getContext(): ?SyncContextInterface
    {
        return $this->Context;
    }

    private function store(): SyncStoreInterface
    {
        return $this->Provider->store();
    }
}
