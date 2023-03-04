<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Closure;
use Lkrms\Contract\IPipeline;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncFilterPolicy;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncOperation;

/**
 * Provides direct access to an ISyncProvider's implementation of sync
 * operations for an entity
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of ISyncProvider
 * @implements ISyncDefinition<TEntity,TProvider>
 */
abstract class SyncDefinition implements ISyncDefinition
{
    abstract protected function getClosure(int $operation): ?Closure;

    /**
     * The ISyncEntity being serviced
     *
     * @var class-string<TEntity>
     */
    protected $Entity;

    /**
     * The ISyncProvider servicing the entity
     *
     * @var TProvider
     */
    protected $Provider;

    /**
     * A list of supported sync operations
     *
     * @var int[]
     * @psalm-var array<SyncOperation::*>
     * @see SyncOperation
     */
    protected $Operations;

    /**
     * The conformity level of data returned by the provider for this entity
     *
     * Use {@see ArrayKeyConformity::COMPLETE} or
     * {@see ArrayKeyConformity::PARTIAL} wherever possible to improve
     * performance.
     *
     * @var int
     * @psalm-var ArrayKeyConformity::*
     * @see ArrayKeyConformity
     */
    protected $Conformity;

    /**
     * The action to take when filters are ignored by the provider
     *
     * To prevent a request for entities that meet one or more criteria
     * inadvertently reaching the backend as a request for a larger set of
     * entities--if not all of them--the default policy if there are unclaimed
     * filters is {@see SyncFilterPolicy::THROW_EXCEPTION}. See
     * {@see SyncFilterPolicy} for alternative policies or
     * {@see \Lkrms\Sync\Contract\ISyncContext::withArgs()} for more information
     * about filters.
     *
     * @var int
     * @psalm-var SyncFilterPolicy::*
     */
    protected $FilterPolicy;

    /**
     * @var array<int,Closure>
     * @psalm-var array<SyncOperation::*,Closure>
     */
    protected $Overrides;

    /**
     * A pipeline that maps data from the provider to entity-compatible
     * associative arrays, or `null` if mapping is not required
     *
     * @var IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null
     */
    protected $DataToEntityPipeline;

    /**
     * A pipeline that maps serialized entities to data compatible with the
     * provider, or `null` if mapping is not required
     *
     * @var IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null
     */
    protected $EntityToDataPipeline;

    /**
     * @internal
     * @var SyncIntrospector<TEntity>
     */
    protected $EntityIntrospector;

    /**
     * @internal
     * @var SyncIntrospector<TProvider>
     */
    protected $ProviderIntrospector;

    /**
     * @var array<int,Closure>
     * @psalm-var array<SyncOperation::*,Closure>
     */
    private $Closures = [];

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param int[] $operations
     * @psalm-param array<SyncOperation::*> $operations
     * @psalm-param ArrayKeyConformity::* $conformity
     * @psalm-param SyncFilterPolicy::* $filterPolicy
     * @param array<int,Closure> $overrides
     * @psalm-param array<SyncOperation::*,Closure> $overrides
     * @param IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $dataToEntityPipeline
     * @param IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $entityToDataPipeline
     */
    public function __construct(string $entity, ISyncProvider $provider, array $operations = [], int $conformity = ArrayKeyConformity::NONE, int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION, array $overrides = [], ?IPipeline $dataToEntityPipeline = null, ?IPipeline $entityToDataPipeline = null)
    {
        $this->Entity               = $entity;
        $this->Provider             = $provider;
        $this->Conformity           = $conformity;
        $this->FilterPolicy         = $filterPolicy;
        $this->DataToEntityPipeline = $dataToEntityPipeline;
        $this->EntityToDataPipeline = $entityToDataPipeline;

        // Combine overridden operations with $operations and discard any
        // invalid values
        $this->Operations = array_intersect(
            SyncOperation::getAll(),
            array_merge(array_values($operations), array_keys($overrides))
        );
        // Discard any overrides for invalid operations
        $this->Overrides = array_intersect_key($overrides, array_flip($this->Operations));

        $this->EntityIntrospector   = SyncIntrospector::get($entity);
        $this->ProviderIntrospector = SyncIntrospector::get(get_class($provider));
    }

    final public function getSyncOperationClosure(int $operation): ?Closure
    {
        // Return a previous result if possible
        if (array_key_exists($operation, $this->Closures)) {
            return $this->Closures[$operation];
        }

        // Overrides take precedence over everything else, including declared
        // methods
        if (array_key_exists($operation, $this->Overrides)) {
            return $this->Closures[$operation] = $this->Overrides[$operation];
        }

        // If a method has been declared for this operation, use it, even if
        // it's not in $this->Operations
        if ($closure =
                $this->ProviderIntrospector->getDeclaredSyncOperationClosure(
                    $operation,
                    $this->EntityIntrospector,
                    $this->Provider
                )) {
            return $this->Closures[$operation] = $closure;
        }

        // Return null if the operation doesn't appear in $this->Operations
        if (!array_key_exists($operation, $this->Operations)) {
            return $this->Closures[$operation] = null;
        }

        // Otherwise, request a closure from the subclass
        return $this->Closures[$operation] = $this->getClosure($operation);
    }

    /**
     * Get an entity-to-data pipeline for the entity
     *
     * @return IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>
     */
    final protected function getPipelineToBackend(): IPipeline
    {
        /** @var IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}> */
        $pipeline = $this->EntityToDataPipeline
            ?: Pipeline::create();

        /** @var IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}> */
        $pipeline = $pipeline->after(
            fn(ISyncEntity $payload) => $payload->toArray()
        );

        return $pipeline;
    }

    /**
     * Get a data-to-entity pipeline for the entity
     *
     * Before returning the pipeline:
     * - a closure to create instances of the entity from arrays returned by the
     *   pipeline is applied via {@see IPipeline::then()}
     * - the definition's {@see SyncDefinition::$Conformity} is applied via
     *   {@see IPipeline::withConformity()}
     *
     * @return IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>
     */
    final protected function getPipelineToEntity(): IPipeline
    {
        /** @var IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}> */
        $pipeline = $this->DataToEntityPipeline
            ?: Pipeline::create();

        return $pipeline
            ->withConformity($this->Conformity)
            ->then(
                function (array $data, IPipeline $pipeline, $arg) use (&$ctx, &$closure) {
                    if (!$ctx) {
                        /** @var ISyncContext $ctx */
                        [, $ctx] = $arg;
                        $ctx     = $ctx->withConformity($this->Conformity);
                    }
                    if (!$closure) {
                        $closure = in_array($this->Conformity, [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE])
                            ? SyncIntrospector::getService($ctx->container(), $this->Entity)->getCreateSyncEntityFromSignatureClosure(array_keys($data))
                            : SyncIntrospector::getService($ctx->container(), $this->Entity)->getCreateSyncEntityFromClosure();
                    }
                    /** @var TEntity */
                    $entity = $closure($data, $this->Provider, $ctx);

                    return $entity;
                }
            );
    }
}
