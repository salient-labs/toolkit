<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Thrown when an unimplemented sync operation is attempted
 *
 */
class SyncOperationNotImplementedException extends SyncException
{
    /**
     * @param class-string<ISyncEntity> $entity
     * @param int&SyncOperation::* $operation
     */
    public function __construct(ISyncProvider $provider, string $entity, int $operation)
    {
        parent::__construct(sprintf(
            '%s has not implemented SyncOperation::%s for %s',
            get_class($provider),
            SyncOperation::toName($operation),
            $entity
        ));
    }
}
