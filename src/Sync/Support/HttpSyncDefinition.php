<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\IPipeline;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Sync\SyncOperation;

class HttpSyncDefinition extends SyncDefinition
{
    /**
     * @var string
     */
    protected $Path;

    /**
     * @var int[]
     */
    protected $Operations;

    /**
     * @var array<int,Closure>
     */
    protected $Overrides;

    /**
     * @param int[] $operations
     * @param array<int,Closure> $overrides
     */
    public function __construct(string $entity, HttpSyncProvider $provider, string $path, array $operations, array $overrides = [], ?IPipeline $dataToEntityPipeline = null, ?IPipeline $entityToDataPipeline = null)
    {
        parent::__construct($entity, $provider, $dataToEntityPipeline, $entityToDataPipeline);
        $this->Path       = $path;
        $this->Operations = array_intersect(
            SyncOperation::getAll(),
            array_merge(array_values($operations), array_keys($overrides))
        );
        $this->Overrides = array_intersect_key($overrides, array_flip($this->Operations));
    }

    public function getSyncOperationClosure(int $operation): ?Closure
    {
        if (!in_array($operation, $this->Operations))
        {
            return null;
        }

        switch ($operation)
        {
            case SyncOperation::CREATE:
                break;
            case SyncOperation::READ:
                break;
            case SyncOperation::UPDATE:
                break;
            case SyncOperation::DELETE:
                break;
            case SyncOperation::CREATE_LIST:
                break;
            case SyncOperation::READ_LIST:
                break;
            case SyncOperation::UPDATE_LIST:
                break;
            case SyncOperation::DELETE_LIST:
                break;
        }

        return null;
    }

}
