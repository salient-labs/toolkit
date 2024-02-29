<?php declare(strict_types=1);

namespace Salient\Sync\Event;

use Salient\Contract\Sync\SyncStoreLoadedEventInterface;

/**
 * Dispatched when an entity store is loaded
 */
class SyncStoreLoadedEvent extends AbstractSyncStoreEvent implements SyncStoreLoadedEventInterface {}
