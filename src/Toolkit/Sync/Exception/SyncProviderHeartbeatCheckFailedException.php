<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Sync\SyncStore;

/**
 * Thrown when an entity store's provider heartbeat check fails
 *
 * @see SyncStore::checkHeartbeats()
 */
class SyncProviderHeartbeatCheckFailedException extends AbstractSyncException
{
    /** @var SyncProviderInterface[] */
    protected $Providers;

    public function __construct(SyncProviderInterface ...$provider)
    {
        $this->Providers = $provider;

        $count = count($provider);
        parent::__construct(
            $count === 1
                ? 'Provider backend unreachable'
                : sprintf('%d provider backends unreachable', $count)
        );
    }
}
