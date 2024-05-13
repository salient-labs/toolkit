<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Thrown when a sync operation is unable to resolve its own parameters from the
 * context object it receives
 */
class SyncInvalidContextException extends AbstractSyncException
{
    /** @var SyncContextInterface */
    protected $Context;
    /** @var SyncProviderInterface */
    protected $Provider;
    /** @var class-string<SyncEntityInterface> */
    protected $Entity;
    /** @var SyncOperation::* */
    protected $Operation;

    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param SyncOperation::* $operation
     */
    public function __construct(string $message, SyncContextInterface $context, SyncProviderInterface $provider, string $entity, $operation)
    {
        $this->Context = clone $context;
        $this->Provider = $provider;
        $this->Entity = $entity;
        $this->Operation = $operation;

        parent::__construct($message);
    }
}
