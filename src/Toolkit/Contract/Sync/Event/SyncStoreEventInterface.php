<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * Base interface for entity store events
 */
interface SyncStoreEventInterface
{
    public function store(): SyncStoreInterface;
}
