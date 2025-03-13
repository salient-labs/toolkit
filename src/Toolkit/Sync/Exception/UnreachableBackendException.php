<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\UnreachableBackendExceptionInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Throwable;

class UnreachableBackendException extends SyncException implements UnreachableBackendExceptionInterface
{
    protected SyncProviderInterface $Provider;

    public function __construct(
        SyncProviderInterface $provider,
        string $message = '',
        ?Throwable $previous = null
    ) {
        $this->Provider = $provider;

        parent::__construct($message, $previous);
    }

    public function getProvider(): SyncProviderInterface
    {
        return $this->Provider;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(): array
    {
        return [
            'Provider' => $this->getProviderName($this->Provider),
        ];
    }
}
