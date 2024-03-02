<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncOperation;

/**
 * Thrown when the non-mandatory arguments passed to a sync operation do not
 * represent a valid filter
 *
 * See {@see SyncContextInterface::withFilter()} for valid signatures.
 */
class SyncInvalidFilterSignatureException extends SyncInvalidFilterException
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
