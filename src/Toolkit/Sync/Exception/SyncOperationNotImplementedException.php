<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\Exception\SyncOperationNotImplementedExceptionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Utility\Reflect;

/**
 * @api
 */
class SyncOperationNotImplementedException extends AbstractSyncException implements SyncOperationNotImplementedExceptionInterface
{
    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param SyncOperation::* $operation
     */
    public function __construct(SyncProviderInterface $provider, string $entity, int $operation)
    {
        parent::__construct(sprintf(
            '%s has not implemented SyncOperation::%s for %s',
            $this->getProviderName($provider),
            Reflect::getConstantName(SyncOperation::class, $operation),
            $entity,
        ));
    }
}
