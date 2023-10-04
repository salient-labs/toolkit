<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Concept\FluentInterface;
use Lkrms\Concern\TReadable;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\IReadable;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Catalog\SyncOperations;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Exception\SyncFilterPolicyViolationException;
use Lkrms\Sync\Support\SyncIntrospectionClass;
use Lkrms\Sync\Support\SyncIntrospector;
use Closure;
use LogicException;

/**
 * Provides direct access to an ISyncProvider's implementation of sync
 * operations for an entity
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of ISyncProvider
 *
 * @property-read class-string<TEntity> $Entity The ISyncEntity being serviced
 * @property-read TProvider $Provider The ISyncProvider servicing the entity
 * @property-read array<SyncOperation::*> $Operations A list of supported sync operations
 * @property-read ArrayKeyConformity::* $Conformity The conformity level of data returned by the provider for this entity
 * @property-read SyncFilterPolicy::* $FilterPolicy The action to take when filters are unclaimed by the provider
 * @property-read array<SyncOperation::*,Closure(ISyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): mixed> $Overrides An array that maps sync operations to closures that override any other implementations
 * @property-read IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $PipelineFromBackend A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required
 * @property-read IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $PipelineToBackend A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required
 * @property-read SyncEntitySource::*|null $ReturnEntitiesFrom Where to acquire entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 *
 * @implements ISyncDefinition<TEntity,TProvider>
 */
abstract class SyncDefinition extends FluentInterface implements ISyncDefinition, IReadable
{
    use TReadable;

    /**
     * Return a closure to perform a sync operation on the entity
     *
     * This method is called if `$operation` is found in
     * {@see SyncDefinition::$Operations}.
     *
     * @param SyncOperation::* $operation
     * @phpstan-return (
     *     $operation is SyncOperation::READ
     *     ? (Closure(ISyncContext, int|string|null, mixed...): TEntity)
     *     : (
     *         $operation is SyncOperation::READ_LIST
     *         ? (Closure(ISyncContext, mixed...): iterable<TEntity>)
     *         : (
     *             $operation is SyncOperation::CREATE|SyncOperation::UPDATE|SyncOperation::DELETE
     *             ? (Closure(ISyncContext, TEntity, mixed...): TEntity)
     *             : (Closure(ISyncContext, iterable<TEntity>, mixed...): iterable<TEntity>)
     *         )
     *     )
     * )|null
     */
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
     * @var array<SyncOperation::*>
     */
    protected $Operations;

    /**
     * The conformity level of data returned by the provider for this entity
     *
     * Use {@see ArrayKeyConformity::COMPLETE} or
     * {@see ArrayKeyConformity::PARTIAL} wherever possible to improve
     * performance.
     *
     * @var ArrayKeyConformity::*
     */
    protected $Conformity;

    /**
     * The action to take when filters are unclaimed by the provider
     *
     * To prevent a request for entities that meet one or more criteria
     * inadvertently reaching the backend as a request for a larger set of
     * entities--if not all of them--the default policy if there are unclaimed
     * filters is {@see SyncFilterPolicy::THROW_EXCEPTION}. See
     * {@see SyncFilterPolicy} for alternative policies and
     * {@see ISyncContext::withArgs()} for more information about filters.
     *
     * @var SyncFilterPolicy::*
     */
    protected $FilterPolicy;

    /**
     * An array that maps sync operations to closures that override any other
     * implementations
     *
     * An {@see ISyncDefinition} instance and {@see SyncOperation} value are
     * passed to closures in {@see SyncDefinition::$Overrides} via two arguments
     * inserted before the operation's arguments.
     *
     * Operations implemented here don't need to be added to
     * {@see SyncDefinition::$Operations}.
     *
     * @var array<SyncOperation::*,Closure(ISyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): mixed>
     */
    protected $Overrides;

    /**
     * A pipeline that maps data from the provider to entity-compatible
     * associative arrays, or `null` if mapping is not required
     *
     * @var IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null
     */
    protected $PipelineFromBackend;

    /**
     * A pipeline that maps serialized entities to data compatible with the
     * provider, or `null` if mapping is not required
     *
     * @var IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null
     */
    protected $PipelineToBackend;

    /**
     * Where to acquire entity data for the return value of a successful CREATE,
     * UPDATE or DELETE operation
     *
     * @var SyncEntitySource::*|null
     */
    protected $ReturnEntitiesFrom;

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
     * @var array<SyncOperation::*,Closure>
     */
    private $Closures = [];

    /**
     * @var static|null
     */
    private $WithoutOverrides;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param array<SyncOperation::*> $operations
     * @param ArrayKeyConformity::* $conformity
     * @param SyncFilterPolicy::* $filterPolicy
     * @param array<SyncOperation::*,Closure(ISyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): mixed> $overrides
     * @param IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineFromBackend
     * @param IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineToBackend
     * @param SyncEntitySource::*|null $returnEntitiesFrom
     */
    public function __construct(
        string $entity,
        ISyncProvider $provider,
        array $operations = [],
        int $conformity = ArrayKeyConformity::NONE,
        int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION,
        array $overrides = [],
        ?IPipeline $pipelineFromBackend = null,
        ?IPipeline $pipelineToBackend = null,
        ?int $returnEntitiesFrom = null
    ) {
        $this->Entity = $entity;
        $this->Provider = $provider;
        $this->Conformity = $conformity;
        $this->FilterPolicy = $filterPolicy;
        $this->PipelineFromBackend = $pipelineFromBackend;
        $this->PipelineToBackend = $pipelineToBackend;
        $this->ReturnEntitiesFrom = $returnEntitiesFrom;

        // Combine overridden operations with $operations and discard any
        // invalid values
        $this->Operations = array_intersect(
            SyncOperations::ALL,
            array_merge(array_values($operations), array_keys($overrides))
        );
        // Discard any overrides for invalid operations
        $this->Overrides = array_intersect_key($overrides, array_flip($this->Operations));

        $this->EntityIntrospector = SyncIntrospector::get($entity);
        $this->ProviderIntrospector = SyncIntrospector::get(get_class($provider));
    }

    public function __clone()
    {
        $this->Closures = [];
        $this->WithoutOverrides = null;
    }

    /**
     * Get a closure that uses the provider to perform a sync operation on the
     * entity
     *
     * @param SyncOperation::* $operation
     */
    final public function getSyncOperationClosure(int $operation): ?Closure
    {
        // Return a previous result if possible
        if (array_key_exists($operation, $this->Closures)) {
            return $this->Closures[$operation];
        }

        // Overrides take precedence over everything else, including declared
        // methods
        if (array_key_exists($operation, $this->Overrides)) {
            return $this->Closures[$operation] =
                fn(ISyncContext $ctx, ...$args) =>
                    $this->Overrides[$operation](
                        $this,
                        $operation,
                        $this->getContextWithFilterCallback($operation, $ctx),
                        ...$args
                    );
        }

        // If a method has been declared for this operation, use it, even if
        // it's not in $this->Operations
        if ($closure =
                $this->ProviderIntrospector->getDeclaredSyncOperationClosure(
                    $operation,
                    $this->EntityIntrospector,
                    $this->Provider
                )) {
            return $this->Closures[$operation] =
                fn(ISyncContext $ctx, ...$args) =>
                    $closure(
                        $this->getContextWithFilterCallback($operation, $ctx),
                        ...$args
                    );
        }

        // Return null if the operation doesn't appear in $this->Operations
        if (!in_array($operation, $this->Operations, true)) {
            return $this->Closures[$operation] = null;
        }

        // Otherwise, request a closure from the subclass
        return $this->Closures[$operation] = $this->getClosure($operation);
    }

    /**
     * Ignoring defined overrides, get a closure that uses the provider to
     * perform a sync operation on the entity
     *
     * Useful within overrides when a fallback implementation is required.
     *
     * @param SyncOperation::* $operation
     * @see SyncDefinition::$Overrides
     */
    final public function getFallbackSyncOperationClosure(int $operation): ?Closure
    {
        if (!($clone = $this->WithoutOverrides)) {
            $clone = clone $this;
            $clone->Overrides = [];
            $this->WithoutOverrides = $clone;
        }

        return $clone->getSyncOperationClosure($operation);
    }

    /**
     * Get an entity-to-data pipeline for the entity
     *
     * Before returning the pipeline:
     * - a pipe that serializes any unserialized {@see ISyncEntity} instances is
     *   added via {@see IPipeline::through()}
     *
     * @return IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>
     */
    final protected function getPipelineToBackend(): IPipeline
    {
        /** @var IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}> */
        $pipeline = $this->PipelineToBackend ?: Pipeline::create();

        /** @var IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}> */
        $pipeline = $pipeline->through(
            fn($payload, Closure $next) =>
                $payload instanceof ISyncEntity
                    ? $next($payload->toArray())
                    : $next($payload)
        );

        return $pipeline;
    }

    /**
     * Get a data-to-entity pipeline for the entity
     *
     * Before returning the pipeline:
     * - a closure to create instances of the entity from arrays returned by the
     *   pipeline is applied via {@see IPipeline::then()}
     * - a closure to discard `null` results is applied via
     *   {@see IPipeline::unlessIf()}
     * - the definition's {@see SyncDefinition::$Conformity} is applied via
     *   {@see IPipeline::withConformity()}
     *
     * @return IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>
     */
    final protected function getPipelineFromBackend(): IPipeline
    {
        /** @var IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}> */
        $pipeline = $this->PipelineFromBackend ?: Pipeline::create();

        return $pipeline
            ->withConformity($this->Conformity)
            ->then(
                function (array $data, IPipeline $pipeline, $arg) use (&$ctx, &$closure) {
                    if (!$ctx) {
                        /** @var ISyncContext $ctx */
                        [, $ctx] = $arg;
                        $ctx = $ctx->withConformity($this->Conformity);
                    }
                    if (!$closure) {
                        $closure = in_array(
                            $this->Conformity,
                            [ArrayKeyConformity::PARTIAL, ArrayKeyConformity::COMPLETE]
                        )
                            ? SyncIntrospector::getService($ctx->container(), $this->Entity)
                                ->getCreateSyncEntityFromSignatureClosure(array_keys($data))
                            : SyncIntrospector::getService($ctx->container(), $this->Entity)
                                ->getCreateSyncEntityFromClosure();
                    }
                    /** @var TEntity */
                    $entity = $closure($data, $this->Provider, $ctx);

                    return $entity;
                }
            )
            ->unlessIf(fn($entity) => is_null($entity));
    }

    /**
     * Enforce the unclaimed filter policy
     *
     * @param SyncOperation::* $operation
     * @param array{}|null $empty
     *
     * @see SyncDefinition::$FilterPolicy
     */
    final protected function applyFilterPolicy(int $operation, ISyncContext $ctx, ?bool &$returnEmpty, &$empty): void
    {
        $returnEmpty = false;

        if (SyncFilterPolicy::IGNORE === $this->FilterPolicy ||
                !($filter = $ctx->getFilters())) {
            return;
        }

        switch ($this->FilterPolicy) {
            case SyncFilterPolicy::THROW_EXCEPTION:
                throw new SyncFilterPolicyViolationException($this->Provider, $this->Entity, $filter);

            case SyncFilterPolicy::RETURN_EMPTY:
                $returnEmpty = true;
                $empty = SyncOperation::isList($operation) ? [] : null;

                return;

            case SyncFilterPolicy::FILTER_LOCALLY:
                /** @todo Implement SyncFilterPolicy::FILTER_LOCALLY */
                break;
        }

        throw new LogicException("SyncFilterPolicy invalid or not implemented: {$this->FilterPolicy}");
    }

    /**
     * @param SyncOperation::* $operation
     */
    private function getContextWithFilterCallback(int $operation, ISyncContext $ctx): ISyncContext
    {
        return $ctx->withFilterPolicyCallback(
            function (ISyncContext $ctx, ?bool &$returnEmpty, &$empty) use ($operation): void {
                $this->applyFilterPolicy($operation, $ctx, $returnEmpty, $empty);
            }
        );
    }

    public static function getReadable(): array
    {
        return [
            'Entity',
            'Provider',
            'Operations',
            'Conformity',
            'FilterPolicy',
            'Overrides',
            'PipelineFromBackend',
            'PipelineToBackend',
            'ReturnEntitiesFrom',
        ];
    }
}
