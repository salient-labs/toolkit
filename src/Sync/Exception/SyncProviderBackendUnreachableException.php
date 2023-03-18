<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Facade\Convert;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Thrown when a sync provider's heartbeat check fails
 *
 */
class SyncProviderBackendUnreachableException extends \Lkrms\Exception\Exception
{
    public function __construct(ISyncProvider ...$provider)
    {
        parent::__construct(sprintf(
            'Provider %s unreachable',
            Convert::plural(count($provider), 'backend is', 'backends are')
        ));
    }
}
