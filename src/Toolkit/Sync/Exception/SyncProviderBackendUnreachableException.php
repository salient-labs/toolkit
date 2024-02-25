<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Sync\Contract\ISyncProvider;
use Throwable;

/**
 * Thrown by a sync provider when it can't establish a connection with its
 * backend
 */
class SyncProviderBackendUnreachableException extends SyncException
{
    /**
     * @var ISyncProvider|null
     */
    protected $Provider;

    public function __construct(
        string $message = '',
        ?ISyncProvider $provider = null,
        ?Throwable $previous = null
    ) {
        $this->Provider = $provider;

        parent::__construct($message, $previous);
    }
}
