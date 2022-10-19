<?php

declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Contract\IProvider;
use Lkrms\Sync\Support\SyncEntityProvider;

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
     * Use an entity-agnostic interface to the provider's implementation of sync
     * operations for an entity
     *
     * @todo Create `ISyncEntityProvider` and update return type.
     * @param ISyncContext|\Lkrms\Contract\IContainer|null $context
     */
    public function with(string $syncEntity, $context = null): SyncEntityProvider;

}
