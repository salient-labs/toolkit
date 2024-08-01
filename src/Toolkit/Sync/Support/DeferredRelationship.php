<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\DeferredRelationshipInterface;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Utility\Get;
use ArrayIterator;
use Closure;
use LogicException;
use Traversable;

/**
 * The promise of a sync entity relationship that hasn't been retrieved yet
 *
 * @template TEntity of SyncEntityInterface
 *
 * @implements DeferredRelationshipInterface<TEntity>
 */
final class DeferredRelationship implements DeferredRelationshipInterface
{
    private SyncProviderInterface $Provider;
    private ?SyncContextInterface $Context;
    /** @var class-string<TEntity> */
    private string $Entity;
    /** @var class-string<SyncEntityInterface> */
    private string $ForEntity;
    /** @phpstan-ignore property.onlyWritten */
    private string $ForEntityProperty;
    /** @var int|string */
    private $ForEntityId;
    /** @var array<string,mixed>|null */
    private ?array $Filter;
    /** @var TEntity[]|static|null */
    private $Replace = null;
    /** @var (Closure(TEntity[]): void)|null */
    private ?Closure $Callback = null;
    /** @var TEntity[]|null */
    private ?array $Resolved = null;

    /**
     * Creates a new DeferredRelationship object
     *
     * @param class-string<TEntity> $entity
     * @param class-string<SyncEntityInterface> $forEntity
     * @param int|string $forEntityId
     * @param array<string,mixed>|null $filter
     * @param TEntity[]|static|null $replace
     * @param (Closure(TEntity[]): void)|null $callback
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
        ?Closure $callback = null
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
            ->getStore()
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
     * @inheritDoc
     */
    public function resolve(): array
    {
        if ($this->Resolved !== null) {
            return $this->Resolved;
        }

        $entities = $this
            ->Provider
            ->with($this->Entity, $this->Context)
            ->getListA(
                $this->Filter ?? [
                    Get::basename($this->ForEntity) => $this->ForEntityId,
                ],
            );

        $this->apply($entities);
        return $entities;
    }

    /**
     * @inheritDoc
     */
    public function replace(array $entities): void
    {
        if ($this->Resolved !== null) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Relationship already resolved');
            // @codeCoverageIgnoreEnd
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
     * @inheritDoc
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
        ?Closure $callback = null
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
     * @inheritDoc
     */
    public function getContext(): ?SyncContextInterface
    {
        return $this->Context;
    }

    private function getStore(): SyncStoreInterface
    {
        return $this->Provider->getStore();
    }
}
