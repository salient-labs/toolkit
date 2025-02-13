<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\EntitySource;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Utility\Reflect;

/**
 * @internal
 */
class SyncInvalidEntitySourceException extends SyncException
{
    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param SyncOperation::* $operation
     * @param EntitySource::*|null $source
     */
    public function __construct(SyncProviderInterface $provider, string $entity, int $operation, ?int $source)
    {
        parent::__construct(sprintf(
            "%s gave unsupported EntitySource '%s' for SyncOperation::%s on %s",
            $this->getProviderName($provider),
            $source === null ? '' : Reflect::getConstantName(EntitySource::class, $source),
            Reflect::getConstantName(SyncOperation::class, $operation),
            $entity,
        ));
    }
}
