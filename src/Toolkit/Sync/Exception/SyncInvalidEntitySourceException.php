<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Sync\Catalog\SyncEntitySource;
use Salient\Sync\Catalog\SyncOperation;
use Salient\Sync\Contract\ISyncEntity;
use Salient\Sync\Contract\ISyncProvider;

/**
 * Thrown when an invalid data source is provided for the return value of a sync
 * operation
 */
class SyncInvalidEntitySourceException extends SyncException
{
    /**
     * @param class-string<ISyncEntity> $entity
     * @param SyncOperation::* $operation
     * @param SyncEntitySource::*|null $source
     */
    public function __construct(ISyncProvider $provider, string $entity, $operation, ?int $source)
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
