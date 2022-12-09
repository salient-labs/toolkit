<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Facade\Convert;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncOperation;

/**
 * Thrown when an unimplemented sync operation is attempted
 *
 */
class SyncOperationNotImplementedException extends \Lkrms\Exception\Exception
{
    /**
     * @param ISyncProvider|string $provider
     */
    public function __construct($provider, string $entity, int $operation)
    {
        if ($provider instanceof ISyncProvider) {
            $provider = get_class($provider);
        }

        parent::__construct(sprintf(
            '%s has not implemented SyncOperation::%s for %s',
            Convert::classToBasename($provider),
            SyncOperation::toName($operation),
            $entity
        ));
    }
}
