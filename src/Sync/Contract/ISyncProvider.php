<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvider;
use Lkrms\Sync\Catalog\FilterPolicy;
use Lkrms\Sync\Support\SyncStore;

/**
 * Base interface for providers that sync entities to and from third-party
 * backends
 *
 * @see \Lkrms\Sync\Concept\SyncProvider
 *
 * @extends IProvider<ISyncContext>
 */
interface ISyncProvider extends IProvider
{
    /**
     * @inheritDoc
     */
    public function getContext(?IContainer $container = null): ISyncContext;

    /**
     * Called when the provider is registered with an entity store
     *
     * @see SyncStore::provider()
     *
     * @return $this
     */
    public function setProviderId(int $providerId);

    /**
     * Get the provider ID assigned to the backend instance by the entity store
     */
    public function getProviderId(): ?int;

    /**
     * Get the provider's implementation of sync operations for an entity
     *
     * @template T of ISyncEntity
     * @param class-string<T> $entity
     * @return ISyncDefinition<T,static>
     */
    public function getDefinition(string $entity): ISyncDefinition;

    /**
     * Get the provider's default unclaimed filter policy
     *
     * @return FilterPolicy::*|null
     */
    public function getFilterPolicy(): ?int;

    /**
     * True if a value is of the correct type and format to be an entity ID
     *
     * @param int|string $id
     * @param class-string<ISyncEntity> $entity
     */
    public function isValidIdentifier($id, string $entity): bool;

    /**
     * Get the provider's entity store
     */
    public function store(): SyncStore;

    /**
     * Use an entity-agnostic interface to the provider's implementation of sync
     * operations for an entity
     *
     * @template T of ISyncEntity
     * @param class-string<T> $entity
     * @param ISyncContext|IContainer|null $context
     * @return ISyncEntityProvider<T>
     */
    public function with(string $entity, $context = null): ISyncEntityProvider;
}
