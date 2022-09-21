<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Provides access to a SyncProvider's implementation of sync operations for an
 * entity
 *
 */
class SyncDefinition implements ISyncDefinition
{
    /**
     * @var string
     */
    protected $SyncEntity;

    /**
     * @var ISyncProvider
     */
    protected $SyncProvider;

    /**
     * @var IContainer|null
     */
    protected $Container;

    /**
     * @var IPipeline|null
     */
    protected $DataToEntityPipeline;

    /**
     * @var IPipeline|null
     */
    protected $EntityToDataPipeline;

    /**
     * @var SyncClosureBuilder
     */
    protected $EntityClosureBuilder;

    /**
     * @var SyncClosureBuilder
     */
    protected $ProviderClosureBuilder;

    public function __construct(string $entity, ISyncProvider $provider, ?IContainer $container = null, ?IPipeline $dataToEntityPipeline = null, ?IPipeline $entityToDataPipeline = null)
    {
        $this->SyncEntity           = $entity;
        $this->SyncProvider         = $provider;
        $this->Container            = $container;
        $this->DataToEntityPipeline = $dataToEntityPipeline;
        $this->EntityToDataPipeline = $entityToDataPipeline;

        $container = $container ?: Container::maybeGetGlobalContainer();
        $this->EntityClosureBuilder   = SyncClosureBuilder::maybeGetBound($container, $entity);
        $this->ProviderClosureBuilder = SyncClosureBuilder::maybeGetBound($container, get_class($provider));
    }

    final public function getSyncEntity(): string
    {
        return $this->SyncEntity;
    }

    final public function getSyncProvider(): ISyncProvider
    {
        return $this->SyncProvider;
    }

    public function getSyncOperationClosure(int $operation): ?Closure
    {
        return null;
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
