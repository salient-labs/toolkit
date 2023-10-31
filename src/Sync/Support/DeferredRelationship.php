<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Sync\Catalog\SyncEntityHydrationFlag as HydrationFlag;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Utility\Convert;
use ArrayIterator;
use IteratorAggregate;
use LogicException;
use Traversable;

/**
 * The promise of a sync entity relationship that hasn't been retrieved yet
 *
 * @template TEntity of ISyncEntity
 *
 * @implements IteratorAggregate<array-key,TEntity>
 */
final class DeferredRelationship implements IteratorAggregate
{
    /**
     * The provider servicing the entity
     *
     * @var ISyncProvider
     */
    private $Provider;

    /**
     * The context within which the provider is servicing the entity
     *
     * @var ISyncContext|null
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
     * @var class-string<ISyncEntity>
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

    /**
     * @var array<TEntity>|DeferredRelationship<TEntity>|null
     */
    private $Replace;

    /**
     * @var int-mask-of<HydrationFlag::*>
     */
    private $Flags;

    /**
     * @var array<TEntity>|null
     */
    private $Resolved;

    /**
     * Creates a new DeferredRelationship object
     *
     * @param class-string<TEntity> $entity
     * @param class-string<ISyncEntity> $forEntity
     * @param int|string $forEntityId
     * @param array<string,mixed>|null $filter
     * @param array<TEntity>|DeferredRelationship<TEntity>|null $replace
     */
    private function __construct(
        ISyncProvider $provider,
        ?ISyncContext $context,
        string $entity,
        string $forEntity,
        string $forEntityProperty,
        $forEntityId,
        ?array $filter,
        &$replace
    ) {
        $this->Provider = $provider;
        $this->Context = $context;
        $this->Entity = $entity;
        $this->ForEntity = $forEntity;
        $this->ForEntityProperty = $forEntityProperty;
        $this->ForEntityId = $forEntityId;
        $this->Filter = $filter;
        $this->Replace = &$replace;
        $this->Replace = $this;

        $this->Flags =
            $context
                ? $context->getHydrationFlags($entity)
                : 0;

        $this
            ->store()
            ->entityType($entity)
            ->entityType($forEntity)
            ->deferredRelationship(
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
     * @param bool|null $offline If `null` (the default), the local entity store
     * is used if its copy of the entities is sufficiently fresh, or if the
     * provider cannot be reached. If `true`, the local entity store is used
     * unconditionally. If `false`, the local entity store is unconditionally
     * ignored.
     * @return TEntity[]
     */
    public function resolve(?bool $offline = null): array
    {
        if ($this->Resolved !== null) {
            return $this->Resolved;
        }

        $provider = $this->Provider->with($this->Entity, $this->Context);

        if ($offline === true) {
            $provider = $provider->offline();
        } elseif ($offline === false) {
            $provider = $provider->online();
        }

        if ($this->Flags & HydrationFlag::NO_FILTER) {
            $entities = $provider->getListA();
        } else {
            $entities = $provider->getListA(
                $this->Filter !== null
                    ? $this->Filter
                    : [
                        Convert::classToBasename($this->ForEntity) =>
                            $this->ForEntityId,
                    ],
            );
        }

        $this->Resolved = $entities;
        $this->Replace = $entities;
        unset($this->Replace);

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
            throw new LogicException('Entity already resolved');
        }

        $this->Resolved = $entities;
        $this->Replace = $entities;
        unset($this->Replace);
    }

    /**
     * Defer retrieval of a sync entity relationship
     *
     * @param ISyncProvider $provider The provider servicing the entity.
     * @param ISyncContext|null $context The context within which the provider
     * is servicing the entity.
     * @param class-string<TEntity> $entity The entity to instantiate.
     * @param class-string<ISyncEntity> $forEntity The entity for which the
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
     */
    public static function defer(
        ISyncProvider $provider,
        ?ISyncContext $context,
        string $entity,
        string $forEntity,
        string $forEntityProperty,
        $forEntityId,
        ?array $filter = null,
        &$replace = null
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
        );
    }

    /**
     * Get the context within which the provider is servicing the entity
     */
    public function getContext(): ?ISyncContext
    {
        return $this->Context;
    }

    private function store(): SyncStore
    {
        return $this->Provider->store();
    }
}
