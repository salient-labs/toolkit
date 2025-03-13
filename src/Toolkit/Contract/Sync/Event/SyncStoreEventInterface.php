<?php declare(strict_types=1);

namespace Salient\Contract\Sync\Event;

use Salient\Contract\Sync\SyncStoreInterface;

interface SyncStoreEventInterface
{
    /**
     * Get the entity store that dispatched the event
     */
    public function getStore(): SyncStoreInterface;
}
