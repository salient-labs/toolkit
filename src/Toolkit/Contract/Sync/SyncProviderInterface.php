<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\ProviderInterface;

/**
 * Base interface for providers that sync entities to and from third-party
 * backends
 *
 * @extends ProviderInterface<SyncContextInterface>
 */
interface SyncProviderInterface extends ProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getContext(?ContainerInterface $container = null): SyncContextInterface;

    /**
     * Get the provider ID assigned to the backend instance by its entity store
     */
    public function getProviderId(): int;

    /**
     * Get the provider's implementation of sync operations for an entity
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return SyncDefinitionInterface<T,$this>
     */
    public function getDefinition(string $entity): SyncDefinitionInterface;

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
     * @param class-string<SyncEntityInterface> $entity
     */
    public function isValidIdentifier($id, string $entity): bool;

    /**
     * Get the provider's entity store
     */
    public function getStore(): SyncStoreInterface;

    /**
     * Use an entity-agnostic interface to the provider's implementation of sync
     * operations for an entity
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return SyncEntityProviderInterface<T>
     */
    public function with(string $entity, ?SyncContextInterface $context = null): SyncEntityProviderInterface;
}
