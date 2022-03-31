<?php

declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Convert;
use Lkrms\Sync\SyncOperation;

/**
 *
 * @package Lkrms
 */
class SyncOperationNotImplementedException extends \Lkrms\Exception
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

