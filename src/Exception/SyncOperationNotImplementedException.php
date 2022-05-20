<?php

declare(strict_types=1);

namespace Lkrms\Exception;

use Lkrms\Sync\SyncOperation;
use Lkrms\Util\Convert;

/**
 * Thrown when an unimplemented sync operation is attempted
 *
 */
class SyncOperationNotImplementedException extends \Lkrms\Exception\Exception
{
    public function __construct(string $provider, string $entity, int $operation)
    {
        parent::__construct(sprintf(
            "%s has not implemented SyncOperation::%s for %s",
            Convert::classToBasename($provider),
            SyncOperation::toName($operation),
            $entity
        ));
    }
}
