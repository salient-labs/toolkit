<?php declare(strict_types=1);

namespace Salient\Sync\Exception;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;

/**
 * Thrown when a sync operation cannot proceed because it received invalid data
 */
class SyncInvalidRequestException extends AbstractSyncException
{
    /** @var SyncProviderInterface */
    protected $Provider;
    /** @var class-string<SyncEntityInterface> */
    protected $Entity;
    /** @var SyncContextInterface */
    protected $Context;
    /** @var SyncOperation::* */
    protected $Operation;
    /** @var mixed[] */
    protected $Args;

    /**
     * @param class-string<SyncEntityInterface> $entity
     * @param SyncOperation::* $operation
     * @param mixed ...$args
     */
    public function __construct(string $message, SyncProviderInterface $provider, string $entity, SyncContextInterface $context, $operation, ...$args)
    {
        $this->Provider = $provider;
        $this->Entity = $entity;
        $this->Context = clone $context;
        $this->Operation = $operation;
        $this->Args = $args;

        parent::__construct($message);
    }
}
