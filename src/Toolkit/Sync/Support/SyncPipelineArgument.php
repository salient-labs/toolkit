<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Utility\Reflect;
use InvalidArgumentException;

/**
 * @internal
 */
final class SyncPipelineArgument
{
    /**
     * @readonly
     * @var SyncOperation::*
     */
    public int $Operation;

    /** @readonly */
    public SyncContextInterface $Context;

    /**
     * Set for READ operations
     *
     * @readonly
     * @var int|string|null
     */
    public $Id;

    /**
     * Set for CREATE, UPDATE and DELETE operations
     *
     * @readonly
     */
    public ?SyncEntityInterface $Entity;

    /**
     * @readonly
     * @var mixed[]
     */
    public array $Args;

    /**
     * @param SyncOperation::* $operation
     * @param mixed[] $args
     * @param int|string|null $id May be given for READ operations.
     * @param SyncEntityInterface|null $entity Must be given for CREATE, UPDATE
     * and DELETE operations. Current payload must be applied to variable passed
     * by reference for CREATE_LIST, UPDATE_LIST and DELETE_LIST operations.
     */
    public function __construct(
        int $operation,
        SyncContextInterface $context,
        array $args,
        $id = null,
        ?SyncEntityInterface &$entity = null
    ) {
        $this->Operation = $operation;
        $this->Context = $context;
        $this->Args = $args;

        switch ($operation) {
            case SyncOperation::READ:
                $this->Id = $id;
                break;

            case SyncOperation::CREATE:
            case SyncOperation::UPDATE:
            case SyncOperation::DELETE:
                if ($entity === null) {
                    throw new InvalidArgumentException(sprintf(
                        '$entity required for SyncOperation::%s',
                        Reflect::getConstantName(SyncOperation::class, $operation),
                    ));
                }
                $this->Entity = $entity;
                break;

            case SyncOperation::CREATE_LIST:
            case SyncOperation::UPDATE_LIST:
            case SyncOperation::DELETE_LIST:
                $this->Entity = &$entity;
                break;
        }
    }
}
