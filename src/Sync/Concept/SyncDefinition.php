<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Closure;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\PipelineImmutable;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncContext;
use Lkrms\Sync\Support\SyncFilterPolicy;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncOperation;

/**
 * Provides access to an ISyncProvider's implementation of sync operations for
 * an entity
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of ISyncProvider
 * @implements ISyncDefinition<TEntity,TProvider>
 */
abstract class SyncDefinition implements ISyncDefinition
{
    abstract public function getSyncOperationClosure(int $operation): ?Closure;

    /**
     * @var class-string<TEntity>
     */
    protected $Entity;

    /**
     * @var TProvider
     */
    protected $Provider;

    /**
     * @var int
     * @psalm-var ArrayKeyConformity::*
     */
    protected $Conformity;

    /**
     * @var int
     * @psalm-var SyncFilterPolicy::*
     */
    protected $FilterPolicy;

    /**
     * @var IPipelineImmutable|null
     */
    protected $DataToEntityPipeline;

    /**
     * @var IPipelineImmutable|null
     */
    protected $EntityToDataPipeline;

    /**
     * @var SyncIntrospector<TEntity>
     */
    protected $EntityIntrospector;

    /**
     * @var SyncIntrospector<TProvider>
     */
    protected $ProviderIntrospector;

    /**
     * @psalm-param ArrayKeyConformity::* $conformity
     * @param int $filterPolicy One of the {@see SyncFilterPolicy} values.
     *
     * To prevent, say, a filtered {@see SyncOperation::READ_LIST} request
     * returning every entity of that type when a provider doesn't use the
     * filter, the default policy is {@see SyncFilterPolicy::THROW_EXCEPTION}.
     *
     * See {@see \Lkrms\Sync\Contract\ISyncContext::withArgs()} for more
     * information.
     * @psalm-param SyncFilterPolicy::* $filterPolicy
     * @param IPipelineImmutable|null $dataToEntityPipeline A pipeline that
     * converts data received from the provider to an associative array from
     * which the entity can be instantiated, or `null` if the entity is not
     * supported or conversion is not required.
     * @param IPipelineImmutable|null $entityToDataPipeline A pipeline that
     * converts a serialized instance of the entity to data compatible with the
     * provider, or `null` if the entity is not supported or conversion is not
     * required.
     */
    public function __construct(string $entity, ISyncProvider $provider, int $conformity = ArrayKeyConformity::NONE, int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION, ?IPipelineImmutable $dataToEntityPipeline = null, ?IPipelineImmutable $entityToDataPipeline = null)
    {
        $this->Entity               = $entity;
        $this->Provider             = $provider;
        $this->Conformity           = $conformity;
        $this->FilterPolicy         = $filterPolicy;
        $this->DataToEntityPipeline = $dataToEntityPipeline;
        $this->EntityToDataPipeline = $entityToDataPipeline;

        $this->EntityIntrospector   = SyncIntrospector::get($entity);
        $this->ProviderIntrospector = SyncIntrospector::get(get_class($provider));
    }

    final protected function getPipelineToBackend(): IPipelineImmutable
    {
        return $this->EntityToDataPipeline ?: PipelineImmutable::create();
    }

    final protected function getPipelineToEntity(): IPipelineImmutable
    {
        return ($this->DataToEntityPipeline ?: PipelineImmutable::create())
            ->then(
                function (array $data, IPipeline $pipeline, int $operation, SyncContext $ctx) use (&$closure) {
                    if (!$closure) {
                        $ctx = $ctx->withConformity($this->Conformity);

                        $closure = in_array($this->Conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                            ? SyncIntrospector::getService($ctx->container(), $this->Entity)->getCreateSyncEntityFromSignatureClosure(array_keys($data))
                            : SyncIntrospector::getService($ctx->container(), $this->Entity)->getCreateSyncEntityFromClosure();
                    }

                    return $closure($data, $this->Provider, $ctx);
                }
            );
    }
}
