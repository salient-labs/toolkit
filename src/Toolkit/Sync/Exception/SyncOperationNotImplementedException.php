<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Sync\Catalog\SyncOperation;
use Salient\Sync\Contract\ISyncEntity;
use Salient\Sync\Contract\ISyncProvider;

/**
 * Thrown when an unimplemented sync operation is attempted
 */
class SyncOperationNotImplementedException extends SyncException
{
    /**
     * @param class-string<ISyncEntity> $entity
     * @param SyncOperation::* $operation
     */
    public function __construct(ISyncProvider $provider, string $entity, $operation)
    {
        parent::__construct(sprintf(
            '%s has not implemented SyncOperation::%s for %s',
            get_class($provider),
            SyncOperation::toName($operation),
            $entity
        ));
    }
}
