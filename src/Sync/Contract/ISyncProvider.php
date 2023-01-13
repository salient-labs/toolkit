<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IProvider;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Support\SyncStore;

/**
 * Base interface for SyncEntity providers
 *
 * @see \Lkrms\Sync\Concept\SyncProvider
 */
interface ISyncProvider extends IProvider
{
    /**
     * Called when the provider registers with an entity store
     *
     * See {@see \Lkrms\Sync\Support\SyncStore::provider()} for more
     * information.
     *
     * @return $this
     * @throws \RuntimeException if the provider already has an ID.
     */
    public function setProviderId(int $providerId, string $providerHash);

    /**
     * Get the provider ID currently assigned to the backend instance by the
     * entity store
     *
     */
    public function getProviderId(): int;

    /**
     * Get a stable hash that uniquely identifies the backend instance
     *
     */
    public function getProviderHash(bool $binary = false): string;

    /**
     * Get the provider's entity store
     *
     */
    public function store(): SyncStore;

    /**
     * Use an entity-agnostic interface to the provider's implementation of sync
     * operations for an entity
     *
     * @template T of SyncEntity
     * @param class-string<T> $syncEntity
     * @param ISyncContext|\Lkrms\Contract\IContainer|null $context
     * @return ISyncEntityProvider<T>
     */
    public function with(string $syncEntity, $context = null): ISyncEntityProvider;
}
