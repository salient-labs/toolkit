<?php

declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\IReadable;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Provides access to a SyncProvider's implementation of sync operations for an
 * entity
 *
 */
abstract class SyncDefinition implements ISyncDefinition, IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $SyncEntity;

    /**
     * @var ISyncProvider
     */
    protected $SyncProvider;

    /**
     * @var IPipeline|null
     */
    protected $DataToEntityPipeline;

    /**
     * @var IPipeline|null
     */
    protected $EntityToDataPipeline;

    public function __construct(string $entity, ISyncProvider $provider, ?IPipeline $dataToEntityPipeline = null, ?IPipeline $entityToDataPipeline = null)
    {
        $this->SyncEntity           = $entity;
        $this->SyncProvider         = $provider;
        $this->DataToEntityPipeline = $dataToEntityPipeline;
        $this->EntityToDataPipeline = $entityToDataPipeline;
    }

    final public function getSyncEntity(): string
    {
        return $this->SyncEntity;
    }

    final public function getSyncProvider(): ISyncProvider
    {
        return $this->SyncProvider;
    }

    final public function getDataToEntityPipeline(): ?IPipeline
    {
        return $this->DataToEntityPipeline;
    }

    final public function getEntityToDataPipeline(): ?IPipeline
    {
        return $this->EntityToDataPipeline;
    }

}
