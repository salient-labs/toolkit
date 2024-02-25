<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Sync\Catalog\SyncOperation;
use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncEntity;
use Salient\Sync\Contract\ISyncProvider;

/**
 * Thrown when a sync operation is unable to resolve its own parameters from the
 * context object it receives
 */
class SyncInvalidContextException extends SyncException
{
    /**
     * @var ISyncContext
     */
    protected $Context;

    /**
     * @var ISyncProvider
     */
    protected $Provider;

    /**
     * @var class-string<ISyncEntity>
     */
    protected $Entity;

    /**
     * @var SyncOperation::*
     */
    protected $Operation;

    /**
     * @param class-string<ISyncEntity> $entity
     * @param SyncOperation::* $operation
     */
    public function __construct(string $message, ISyncContext $context, ISyncProvider $provider, string $entity, $operation)
    {
        $this->Context = clone $context;
        $this->Provider = $provider;
        $this->Entity = $entity;
        $this->Operation = $operation;

        parent::__construct($message);
    }
}
