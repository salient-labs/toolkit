<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Thrown when an invalid data source is provided for the return value of a sync
 * operation
 *
 */
class SyncInvalidEntitySourceException extends SyncException
{
    /**
     * @param class-string<ISyncEntity> $entity
     * @param int&SyncOperation::* $operation
     * @param SyncEntitySource::*|null $source
     */
    public function __construct(ISyncProvider $provider, string $entity, int $operation, ?int $source)
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
