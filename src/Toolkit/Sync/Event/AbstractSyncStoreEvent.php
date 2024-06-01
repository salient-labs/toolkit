<?php declare(strict_types=1);

namespace Salient\Sync\Event;

use Salient\Contract\Sync\SyncStoreEventInterface;
use Salient\Contract\Sync\SyncStoreInterface;

/**
 * Base class for entity store events
 */
abstract class AbstractSyncStoreEvent extends AbstractSyncEvent implements SyncStoreEventInterface
{
    protected SyncStoreInterface $Store;

    public function __construct(SyncStoreInterface $store)
    {
        $this->Store = $store;
    }

    public function store(): SyncStoreInterface
    {
        return $this->Store;
    }
}
