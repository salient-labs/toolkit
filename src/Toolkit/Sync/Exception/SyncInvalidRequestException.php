<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Sync\Catalog\SyncOperation;
use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncEntity;
use Salient\Sync\Contract\ISyncProvider;

/**
 * Thrown when a sync operation cannot proceed because it received invalid data
 */
class SyncInvalidRequestException extends SyncException
{
    /**
     * @var ISyncProvider
     */
    protected $Provider;

    /**
     * @var class-string<ISyncEntity>
     */
    protected $Entity;

    /**
     * @var ISyncContext
     */
    protected $Context;

    /**
     * @var SyncOperation::*
     */
    protected $Operation;

    /**
     * @var mixed[]
     */
    protected $Args;

    /**
     * @param class-string<ISyncEntity> $entity
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     */
    public function __construct(string $message, ISyncProvider $provider, string $entity, ISyncContext $context, $operation, ...$args)
    {
        $this->Provider = $provider;
        $this->Entity = $entity;
        $this->Context = clone $context;
        $this->Operation = $operation;
        $this->Args = $args;

        parent::__construct($message);
    }
}
