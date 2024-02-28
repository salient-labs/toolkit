<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Sync\Catalog\SyncOperation;

/**
 * Thrown when an unimplemented sync operation is attempted
 */
class SyncOperationNotImplementedException extends AbstractSyncException
{
    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param SyncOperation::* $operation
     */
    public function __construct(SyncProviderInterface $provider, string $entity, $operation)
    {
        parent::__construct(sprintf(
            '%s has not implemented SyncOperation::%s for %s',
            get_class($provider),
            SyncOperation::toName($operation),
            $entity
        ));
    }
}
