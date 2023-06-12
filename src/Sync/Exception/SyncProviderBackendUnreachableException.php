<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Contract\ISyncProvider;
use Throwable;

/**
 * May be thrown by a provider when it can't establish a connection with its
 * backend
 *
 */
class SyncProviderBackendUnreachableException extends SyncException
{
    /**
     * @var ISyncProvider|null
     */
    protected $Provider;

    public function __construct(string $message = '', ?ISyncProvider $provider = null, ?Throwable $previous = null)
    {
        $this->Provider = $provider;

        parent::__construct($message, $previous);
    }
}
