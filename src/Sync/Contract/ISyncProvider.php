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
     * Use an entity-agnostic interface to the provider's implementation of sync
     * operations for an entity
     *
     * @todo Create `ISyncEntityProvider` and update return type.
     * @param ISyncContext|\Lkrms\Contract\IContainer|null $context
     */
    public function with(string $syncEntity, $context = null): SyncEntityProvider;

}
