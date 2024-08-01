<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Closure;
use IteratorAggregate;

/**
 * @template TEntity of SyncEntityInterface
 *
 * @extends IteratorAggregate<array-key,TEntity>
 */
interface DeferredRelationshipInterface extends IteratorAggregate
{
    /**
     * Get the context within which the provider is servicing the entity
     */
    public function getContext(): ?SyncContextInterface;

    /**
     * Resolve the deferred relationship from the provider or the local entity
     * store
     *
     * Calling this method has the same effect as passing the resolved entities
     * to {@see DeferredRelationshipInterface::replace()}.
     *
     * @return TEntity[]
     */
    public function resolve(): array;

    /**
     * Resolve the deferred relationship with a list of entity instances
     *
     * If `$callback` was given when the relationship was deferred, it is called
     * with the given entities, otherwise the variable or property given via
     * `$replace` is replaced.
     *
     * Subsequent calls to {@see DeferredRelationshipInterface::resolve()}
     * return the same instances.
     *
     * @param TEntity[] $entities
     */
    public function replace(array $entities): void;

    /**
     * Defer retrieval of a sync entity relationship
     *
     * @param SyncProviderInterface $provider The provider servicing the entity.
     * @param SyncContextInterface|null $context The context within which the
     * provider is servicing the entity.
     * @param class-string<TEntity> $entity The entity to instantiate.
     * @param class-string<SyncEntityInterface> $forEntity The entity for which
     * the relationship is deferred.
     * @param string $forEntityProperty The entity property for which the
     * relationship is deferred.
     * @param int|string $forEntityId The identifier of the entity for which the
     * relationship is deferred.
     * @param array<string,mixed>|null $filter Overrides the default filter
     * passed to the provider when requesting entities.
     * @param TEntity[]|static|null $replace Refers to the variable or property
     * to replace when the relationship is resolved.
     * @param (Closure(TEntity[]): void)|null $callback If given, `$replace` is
     * ignored and the resolved entities are passed to the callback.
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
    ): void;
}
