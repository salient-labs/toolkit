<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntityLinkType as LinkType;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Sync\SyncStore;
use LogicException;

/**
 * The promise of a sync entity that hasn't been retrieved yet
 *
 * @template TEntity of SyncEntityInterface
 *
 * @mixin TEntity
 */
final class DeferredEntity
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
     * The identifier of the deferred entity
     *
     * @var int|string
     */
    private $EntityId;

    /**
     * @var TEntity|DeferredEntity<TEntity>|null
     */
    private $Replace;

    /**
     * @var (callable(TEntity): void)|null
     */
    private $Callback;

    /**
     * @var TEntity|null
     */
    private $Resolved;

    /**
     * Creates a new DeferredEntity object
     *
     * @param class-string<TEntity> $entity
     * @param int|string $entityId
     * @param TEntity|DeferredEntity<TEntity>|null $replace
     * @param (callable(TEntity): void)|null $callback
     */
    private function __construct(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        $entityId,
        &$replace,
        ?callable $callback = null
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
            ->store()
            ->entityType($entity)
            ->deferredEntity(
                $this->Provider->getProviderId(),
                $entity,
                $entityId,
                $this,
            );
    }

    /**
     * Get the deferred entity's canonical location in the form of an array
     *
     * @param LinkType::* $type
     * @return array<string,int|string>
     */
    public function toLink(int $type = LinkType::DEFAULT, bool $compact = true): array
    {
        switch ($type) {
            case LinkType::INTERNAL:
            case LinkType::DEFAULT:
                return [
                    '@type' => $this->typeUri($compact),
                    '@id' => $this->EntityId,
                ];

            case LinkType::COMPACT:
                return [
                    '@id' => $this->uri($compact),
                ];
        }

        throw new LogicException("Invalid link type: $type");
    }

    /**
     * Get the deferred entity's canonical location in the form of a URI
     */
    public function uri(bool $compact = true): string
    {
        return sprintf('%s/%s', $this->typeUri($compact), $this->EntityId);
    }

    private function typeUri(bool $compact): string
    {
        $uri = $this->store()->getEntityTypeUri($this->Entity, $compact);

        return
            $uri === null
                ? '/' . str_replace('\\', '/', ltrim($this->Entity, '\\'))
                : $uri;
    }

    /**
     * Resolve the deferred entity from the provider or the local entity store
     *
     * @return TEntity
     */
    public function resolve(): SyncEntityInterface
    {
        if ($this->Resolved !== null) {
            return $this->Resolved;
        }

        $entity =
            $this
                ->Provider
                ->with($this->Entity, $this->Context)
                ->get($this->EntityId);

        $this->Resolved = $entity;
        return $entity;
    }

    /**
     * Resolve the deferred entity with an instance
     *
     * @param TEntity $entity
     */
    public function replace(SyncEntityInterface $entity): void
    {
        if ($this->Resolved !== null) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Entity already resolved');
            // @codeCoverageIgnoreEnd
        }

        $this->Resolved = $entity;
        $this->apply($entity);
    }

    /**
     * @param TEntity $entity
     */
    private function apply(SyncEntityInterface $entity): void
    {
        if ($this->Callback) {
            ($this->Callback)($entity);
            return;
        }

        $this->Replace = $entity;
        unset($this->Replace);
    }

    /**
     * Defer retrieval of a sync entity
     *
     * @param SyncProviderInterface $provider The provider servicing the entity.
     * @param SyncContextInterface|null $context The context within which the provider
     * is servicing the entity.
     * @param class-string<TEntity> $entity The entity to instantiate.
     * @param int|string $entityId The identifier of the deferred entity.
     * @param TEntity|DeferredEntity<TEntity>|null $replace Refers to the
     * variable or property to replace when the entity is resolved. Do not
     * assign anything else to it after calling this method.
     * @param (callable(TEntity): void)|null $callback If given, `$replace` is
     * ignored and the resolved entity is passed to the callback.
     */
    public static function defer(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        $entityId,
        &$replace = null,
        ?callable $callback = null
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
     * Defer retrieval of a list of sync entities
     *
     * @param SyncProviderInterface $provider The provider servicing the entity.
     * @param SyncContextInterface|null $context The context within which the provider
     * is servicing the entity.
     * @param class-string<TEntity> $entity The entity to instantiate.
     * @param array<int|string> $entityIds A list of deferred entity
     * identifiers.
     * @param array<TEntity|DeferredEntity<TEntity>>|null $replace Refers to the
     * variable or property to replace when the entities are resolved. Do not
     * assign anything else to it after calling this method.
     * @param (callable(TEntity): void)|null $callback If given, `$replace` is
     * ignored and the resolved entities are passed to the callback.
     */
    public static function deferList(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        array $entityIds,
        &$replace = null,
        ?callable $callback = null
    ): void {
        if ($callback) {
            unset($replace);
            foreach ($entityIds as $entityId) {
                new self(
                    $provider,
                    $context,
                    $entity,
                    $entityId,
                    $replace,
                    $callback,
                );
            }
            return;
        }

        $i = -1;
        $list = [];
        foreach ($entityIds as $entityId) {
            $list[++$i] = null;
            new self(
                $provider,
                $context,
                $entity,
                $entityId,
                $list[$i],
            );
        }
        $replace = $list;
    }

    /**
     * Get the context within which the provider is servicing the entity
     */
    public function getContext(): ?SyncContextInterface
    {
        return $this->Context;
    }

    private function store(): SyncStore
    {
        return $this->Provider->store();
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
