<?php declare(strict_types=1);

namespace Lkrms\Sync\Exception;

use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;

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
