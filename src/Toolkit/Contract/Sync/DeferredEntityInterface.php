<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Sync\SyncEntityLinkType as LinkType;
use Closure;
use LogicException;

/**
 * @template TEntity of SyncEntityInterface
 */
interface DeferredEntityInterface
{
    /**
     * Get the context within which the provider is servicing the entity
     */
    public function getContext(): ?SyncContextInterface;

    /**
     * Get the deferred entity's canonical location in the form of an array
     *
     * @param LinkType::* $type
     * @return array<string,int|string>
     */
    public function toLink(int $type = LinkType::DEFAULT, bool $compact = true): array;

    /**
     * Get the deferred entity's canonical location in the form of a URI
     */
    public function getUri(bool $compact = true): string;

    /**
     * Resolve the deferred entity from the provider or the local entity store
     *
     * This method returns the resolved entity without taking further action.
     * {@see DeferredEntityInterface::replace()} is called separately.
     *
     * @return TEntity
     */
    public function resolve(): SyncEntityInterface;

    /**
     * Resolve the deferred entity with an instance
     *
     * If `$callback` was given when the entity was deferred, it is called with
     * the given entity, otherwise the variable or property given via `$replace`
     * is replaced.
     *
     * Subsequent calls to {@see DeferredEntityInterface::resolve()} return the
     * same instance.
     *
     * @param TEntity $entity
     * @throws LogicException if the entity has already been resolved.
     */
    public function replace(SyncEntityInterface $entity): void;

    /**
     * Defer retrieval of a sync entity
     *
     * @param SyncProviderInterface $provider The provider servicing the entity.
     * @param SyncContextInterface|null $context The context within which the
     * provider is servicing the entity.
     * @param class-string<TEntity> $entity The entity to instantiate.
     * @param int|string $entityId The identifier of the deferred entity.
     * @param TEntity|static|null $replace Refers to the variable or property to
     * replace when the entity is resolved.
     * @param (Closure(TEntity): void)|null $callback If given, `$replace` is
     * ignored and the resolved entity is passed to the callback.
     */
    public static function defer(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        $entityId,
        &$replace = null,
        ?Closure $callback = null
    ): void;

    /**
     * Defer retrieval of a list of sync entities
     *
     * @param SyncProviderInterface $provider The provider servicing the entity.
     * @param SyncContextInterface|null $context The context within which the
     * provider is servicing the entity.
     * @param class-string<TEntity> $entity The entity to instantiate.
     * @param array<int|string> $entityIds A list of deferred entity
     * identifiers.
     * @param array<TEntity|static>|null $replace Refers to the variable or
     * property to replace when the entities are resolved.
     * @param (Closure(TEntity): void)|null $callback If given, `$replace` is
     * ignored and each resolved entity is passed to the callback.
     */
    public static function deferList(
        SyncProviderInterface $provider,
        ?SyncContextInterface $context,
        string $entity,
        array $entityIds,
        &$replace = null,
        ?Closure $callback = null
    ): void;
}
