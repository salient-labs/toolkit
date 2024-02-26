<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Sync\Catalog\SyncEntitySource;
use Salient\Sync\Catalog\SyncOperation;
use Salient\Sync\Contract\SyncEntityInterface;
use Salient\Sync\Contract\SyncProviderInterface;

/**
 * Thrown when an invalid data source is provided for the return value of a sync
 * operation
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
            get_class($provider),
            $source === null ? '' : SyncEntitySource::toName($source),
            SyncOperation::toName($operation),
            $entity
        ));
    }
}
