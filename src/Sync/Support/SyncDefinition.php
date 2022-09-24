<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Contract\ReturnsContainer;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\PipelineImmutable;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * Provides access to a SyncProvider's implementation of sync operations for an
 * entity
 *
 */
abstract class SyncDefinition implements ISyncDefinition, ReturnsContainer
{
    /**
     * @var string
     */
    protected $Entity;

    /**
     * @var ISyncProvider
     */
    protected $Provider;

    /**
     * @var int
     */
    protected $Conformity;

    /**
     * @var IContainer
     */
    protected $Container;

    /**
     * @var IPipelineImmutable|null
     */
    protected $DataToEntityPipeline;

    /**
     * @var IPipelineImmutable|null
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

    public function __construct(string $entity, ISyncProvider $provider, int $conformity = ArrayKeyConformity::NONE, ?IContainer $container = null, ?IPipelineImmutable $dataToEntityPipeline = null, ?IPipelineImmutable $entityToDataPipeline = null)
    {
        $this->Entity     = $entity;
        $this->Provider   = $provider;
        $this->Conformity = $conformity;
        $this->Container  = $container ?: $provider->container();
        $this->DataToEntityPipeline = $dataToEntityPipeline;
        $this->EntityToDataPipeline = $entityToDataPipeline;

        $container = $container ?: Container::maybeGetGlobalContainer();
        $this->EntityClosureBuilder   = SyncClosureBuilder::maybeGetBound($container, $entity);
        $this->ProviderClosureBuilder = SyncClosureBuilder::maybeGetBound($container, get_class($provider));
    }

    final public function app(): IContainer
    {
        return $this->Container;
    }

    final public function container(): IContainer
    {
        return $this->Container;
    }

    final public function getSyncEntity(): string
    {
        return $this->Entity;
    }

    final public function getSyncProvider(): ISyncProvider
    {
        return $this->Provider;
    }

    abstract public function getSyncOperationClosure(int $operation): ?Closure;

    final public function getDataToEntityPipeline(): ?IPipelineImmutable
    {
        return $this->DataToEntityPipeline;
    }

    final public function getEntityToDataPipeline(): ?IPipelineImmutable
    {
        return $this->EntityToDataPipeline;
    }

    protected function getPipelineToBackend(): IPipelineImmutable
    {
        return $this->EntityToDataPipeline ?: PipelineImmutable::create();
    }

    protected function getPipelineToEntity(): IPipelineImmutable
    {
        return ($this->DataToEntityPipeline ?: PipelineImmutable::create())
            ->then(function (array $entity) use (&$closure)
            {
                if (!$closure)
                {
                    $closure = in_array($this->Conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                        ? $this->EntityClosureBuilder->getCreateFromSignatureClosure(array_keys($entity))
                        : $this->EntityClosureBuilder->getCreateFromClosure();
                }
                /** @todo Add parent from context */
                return $closure($entity, $this->Container);
            });
    }

}
