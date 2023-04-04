<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Closure;
use Lkrms\Concept\FluentInterface;
use Lkrms\Contract\IPipeline;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncFilterPolicy;
use Lkrms\Sync\Support\SyncIntrospectionClass;
use Lkrms\Sync\Support\SyncIntrospector;
use Lkrms\Sync\Support\SyncOperation;
use RuntimeException;
use UnexpectedValueException;

/**
 * Provides direct access to an ISyncProvider's implementation of sync
 * operations for an entity
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of ISyncProvider
 * @implements ISyncDefinition<TEntity,TProvider>
 */
abstract class SyncDefinition extends FluentInterface implements ISyncDefinition
{
    /**
     * Return a closure to perform a sync operation on the entity
     *
     * This method is called if `$operation` is found in
     * {@see SyncDefinition::$Operations}.
     *
     * @phpstan-param SyncOperation::* $operation
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
     * @var int[]
     * @phpstan-var array<SyncOperation::*>
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
     * @phpstan-var ArrayKeyConformity::*
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
     * @phpstan-var SyncFilterPolicy::*
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
     * @var array<int,Closure>
     * ```php
     * fn(ISyncDefinition $def, int $op, ISyncContext $ctx, ...$args)
     * ```
     * @phpstan-var array<SyncOperation::*,Closure(ISyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): mixed>
     */
    protected $Overrides;

    /**
     * A pipeline that maps data from the provider to entity-compatible
     * associative arrays, or `null` if mapping is not required
     *
     * @var IPipeline|null
     * @phpstan-var IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null
     */
    protected $DataToEntityPipeline;

    /**
     * A pipeline that maps serialized entities to data compatible with the
     * provider, or `null` if mapping is not required
     *
     * @var IPipeline|null
     * @phpstan-var IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null
     */
    protected $EntityToDataPipeline;

    /**
     * @internal
     * @var SyncIntrospector<TEntity,SyncIntrospectionClass>
     * @todo Remove ",SyncIntrospectionClass" when template defaults are working
     */
    protected $EntityIntrospector;

    /**
     * @internal
     * @var SyncIntrospector<TProvider,SyncIntrospectionClass>
     * @todo Remove ",SyncIntrospectionClass" when template defaults are working
     */
    protected $ProviderIntrospector;

    /**
     * @var array<int,Closure>
     * @phpstan-var array<SyncOperation::*,Closure>
     */
    private $Closures = [];

    /**
     * @var static|null
     */
    private $WithoutOverrides;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param int[] $operations
     * @phpstan-param array<SyncOperation::*> $operations
     * @phpstan-param ArrayKeyConformity::* $conformity
     * @phpstan-param SyncFilterPolicy::* $filterPolicy
     * @param array<int,Closure> $overrides
     * @phpstan-param array<SyncOperation::*,Closure(ISyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): mixed> $overrides
     * @phpstan-param IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $dataToEntityPipeline
     * @phpstan-param IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $entityToDataPipeline
     */
    public function __construct(
        string $entity,
        ISyncProvider $provider,
        array $operations = [],
        int $conformity = ArrayKeyConformity::NONE,
        int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION,
        array $overrides = [],
        ?IPipeline $dataToEntityPipeline = null,
        ?IPipeline $entityToDataPipeline = null
    ) {
        $this->Entity = $entity;
        $this->Provider = $provider;
        $this->Conformity = $conformity;
        $this->FilterPolicy = $filterPolicy;
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
        if (!array_key_exists($operation, $this->Operations)) {
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
     * @phpstan-param SyncOperation::* $operation
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
     * @phpstan-return IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>
     */
    final protected function getPipelineToBackend(): IPipeline
    {
        /** @var IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}> */
        $pipeline = $this->EntityToDataPipeline ?: Pipeline::create();

        /** @var IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}> */
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
     * @phpstan-return IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>
     */
    final protected function getPipelineToEntity(): IPipeline
    {
        /** @var IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}> */
        $pipeline = $this->DataToEntityPipeline ?: Pipeline::create();

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
     * Enforce the ignored filter policy
     *
     * @phpstan-param SyncOperation::* $operation
     * @see SyncDefinition::$FilterPolicy
     */
    final protected function applyFilterPolicy(int $operation, ISyncContext $ctx, ?bool &$returnEmpty, &$empty): void
    {
        $returnEmpty = false;

        if (SyncFilterPolicy::IGNORE === $this->FilterPolicy ||
                !($filter = $ctx->getFilter())) {
            return;
        }

        switch ($this->FilterPolicy) {
            case SyncFilterPolicy::THROW_EXCEPTION:
                throw new RuntimeException(sprintf(
                    "%s did not claim '%s' from %s filter",
                    get_class($this->Provider),
                    implode("', '", array_keys($filter)),
                    $this->Entity
                ));

            case SyncFilterPolicy::RETURN_EMPTY:
                $returnEmpty = true;
                $empty = SyncOperation::isList($operation) ? [] : null;

                return;

            case SyncFilterPolicy::FILTER_LOCALLY:
                /** @todo Implement SyncFilterPolicy::FILTER_LOCALLY */
                break;
        }

        throw new UnexpectedValueException("SyncFilterPolicy invalid or not implemented: {$this->FilterPolicy}");
    }

    /**
     * @phpstan-param SyncOperation::* $operation
     */
    private function getContextWithFilterCallback(int $operation, ISyncContext $ctx): ISyncContext
    {
        return $ctx->withFilterPolicyCallback(
            function (ISyncContext $ctx, ?bool &$returnEmpty, &$empty) use ($operation): void {
                $this->applyFilterPolicy($operation, $ctx, $returnEmpty, $empty);
            }
        );
    }
}
