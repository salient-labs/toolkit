<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncContext;

/**
 * Thrown when the non-mandatory arguments passed to a sync operation do not
 * represent a valid filter
 *
 * See {@see ISyncContext::withArgs()} for valid signatures.
 */
class SyncInvalidFilterException extends SyncException
{
    /**
     * @var mixed[]
     */
    protected $Args;

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     */
    public function __construct(int $operation, ...$args)
    {
        $this->Args = $args;

        parent::__construct(sprintf(
            'Invalid filter signature for SyncOperation::%s',
            SyncOperation::toName($operation),
        ));
    }
}
