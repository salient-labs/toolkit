<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncEntitySource;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Utility\Reflect;

/**
 * @internal
 */
class SyncInvalidEntitySourceException extends AbstractSyncException
{
    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param SyncOperation::* $operation
     * @param SyncEntitySource::*|null $source
     */
    public function __construct(SyncProviderInterface $provider, string $entity, $operation, ?int $source)
    {
        parent::__construct(sprintf(
            "%s gave unsupported SyncEntitySource '%s' for SyncOperation::%s on %s",
            $this->getProviderName($provider),
            $source === null ? '' : Reflect::getConstantName(SyncEntitySource::class, $source),
            Reflect::getConstantName(SyncOperation::class, $operation),
            $entity,
        ));
    }
}
