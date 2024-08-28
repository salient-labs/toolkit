<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncProviderInterface;
use Throwable;

/**
 * Thrown by a sync provider when it can't establish a connection with its
 * backend
 */
class SyncProviderBackendUnreachableException extends AbstractSyncException
{
    /** @var SyncProviderInterface|null */
    protected $Provider;

    public function __construct(
        string $message = '',
        ?SyncProviderInterface $provider = null,
        ?Throwable $previous = null
    ) {
        $this->Provider = $provider;

        parent::__construct($message, $previous);
    }
}
