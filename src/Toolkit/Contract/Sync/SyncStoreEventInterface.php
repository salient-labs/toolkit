<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Sync\SyncStore;

/**
 * Base interface for entity store events
 */
interface SyncStoreEventInterface
{
    public function store(): SyncStore;
}
