<?php declare(strict_types=1);

namespace Salient\Sync\Event;

use Salient\Contract\Sync\Event\SyncStoreEventInterface;
use Salient\Contract\Sync\SyncStoreInterface;

/**
 * @internal
 */
abstract class AbstractSyncStoreEvent extends AbstractSyncEvent implements SyncStoreEventInterface
{
    protected SyncStoreInterface $Store;

    public function __construct(SyncStoreInterface $store)
    {
        $this->Store = $store;
    }

    /**
     * @inheritDoc
     */
    public function getStore(): SyncStoreInterface
    {
        return $this->Store;
    }
}
