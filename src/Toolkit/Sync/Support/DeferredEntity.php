<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\DeferredEntityInterface;
use Salient\Contract\Sync\LinkType;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Closure;
use LogicException;

/**
 * The promise of a sync entity that hasn't been retrieved yet
 *
 * @template TEntity of SyncEntityInterface
 *
 * @mixin TEntity
 *
 * @implements DeferredEntityInterface<TEntity>
 */
final class DeferredEntity implements DeferredEntityInterface
{
    private SyncProviderInterface $Provider;
    private ?SyncContextInterface $Context;
    /** @var class-string<TEntity> */
    private string $Entity;
    /** @var int|string */
    private $EntityId;
    /** @var TEntity|static|null */
    private $Replace = null;
    /** @var (Closure(TEntity): void)|null */
    private ?Closure $Callback = null;
    /** @var TEntity|null */
    private ?SyncEntityInterface $Resolved = null;

    /**
     * Creates a new DeferredEntity object
     *
     * @param class-string<TEntity> $entity
     * @param int|string $entityId
     * @param TEntity|static|null $replace
     * @param (Closure(TEntity): void)|null $callback
     */
    private function __construct(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        $entityId,
        &$replace,
        ?Closure $callback = null
    ) {
        $this->Provider = $provider;
        $this->Context = $context;
        $this->Entity = $entity;
        $this->EntityId = $entityId;

        if ($callback) {
            $this->Callback = $callback;
        } else {
            $this->Replace = &$replace;
            $this->Replace = $this;
        }

        $this
            ->getStore()
            ->registerEntityType($entity)
            ->deferEntity(
                $this->Provider->getProviderId(),
                $entity,
                $entityId,
                $this,
            );
    }

    /**
     * @inheritDoc
     */
    public function toLink(int $type = LinkType::DEFAULT, bool $compact = true): array
    {
        switch ($type) {
            case LinkType::DEFAULT:
            case LinkType::FRIENDLY:
                return [
                    '@type' => $this->getTypeUri($compact),
                    '@id' => $this->EntityId,
                ];

            case LinkType::COMPACT:
                return [
                    '@id' => $this->getUri($compact),
                ];

            default:
                throw new LogicException("Invalid link type: $type");
        }
    }

    /**
     * @inheritDoc
     */
    public function getUri(bool $compact = true): string
    {
        return sprintf('%s/%s', $this->getTypeUri($compact), $this->EntityId);
    }

    private function getTypeUri(bool $compact): string
    {
        return $this->getStore()->getEntityTypeUri($this->Entity, $compact);
    }

    /**
     * @inheritDoc
     */
    public function resolve(): SyncEntityInterface
    {
        if ($this->Resolved !== null) {
            return $this->Resolved;
        }

        return $this
            ->Provider
            ->with($this->Entity, $this->Context)
            ->get($this->EntityId);
    }

    /**
     * @inheritDoc
     */
    public function replace(SyncEntityInterface $entity): void
    {
        if ($this->Resolved !== null) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Entity already resolved');
            // @codeCoverageIgnoreEnd
        }

        $this->Resolved = $entity;

        if ($this->Callback) {
            ($this->Callback)($entity);
            return;
        }

        $this->Replace = $entity;
        unset($this->Replace);
    }

    /**
     * @inheritDoc
     */
    public static function defer(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        $entityId,
        &$replace = null,
        ?Closure $callback = null
    ): void {
        new self(
            $provider,
            $context,
            $entity,
            $entityId,
            $replace,
            $callback,
        );
    }

    /**
     * @inheritDoc
     */
    public static function deferList(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        array $entityIds,
        &$replace = null,
        ?Closure $callback = null
    ): void {
        if ($callback) {
            foreach ($entityIds as $entityId) {
                /** @disregard P1008 */
                new self(
                    $provider,
                    $context,
                    $entity,
                    $entityId,
                    $null,
                    $callback,
                );
            }
            return;
        }

        $list = [];
        $i = 0;
        foreach ($entityIds as $entityId) {
            new self(
                $provider,
                $context,
                $entity,
                $entityId,
                $list[$i++],
            );
        }
        $replace = $list;
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

    /**
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        $entity = $this->resolve();
        $entity->{$name} = $value;
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        $entity = $this->resolve();
        return $entity->{$name};
    }

    public function __isset(string $name): bool
    {
        $entity = $this->resolve();
        return isset($entity->{$name});
    }

    public function __unset(string $name): void
    {
        $entity = $this->resolve();
        unset($entity->{$name});
    }
}
