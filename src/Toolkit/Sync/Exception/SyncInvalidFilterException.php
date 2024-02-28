<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Sync\Catalog\SyncOperation;

/**
 * Thrown when the non-mandatory arguments passed to a sync operation do not
 * represent a valid filter
 *
 * See {@see SyncContextInterface::withArgs()} for valid signatures.
 */
class SyncInvalidFilterException extends AbstractSyncException
{
    /**
     * @var mixed[]
     */
    protected $Args;

    /**
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     */
    public function __construct($operation, ...$args)
    {
        $this->Args = $args;

        parent::__construct(sprintf(
            'Invalid filter signature for SyncOperation::%s',
            SyncOperation::toName($operation),
        ));
    }
}
