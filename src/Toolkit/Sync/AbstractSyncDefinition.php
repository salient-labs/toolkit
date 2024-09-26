<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Contract\Core\ArrayMapperFlag;
use Salient\Contract\Core\Chainable;
use Salient\Contract\Core\ListConformity;
use Salient\Contract\Core\Readable;
use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Contract\Sync\EntitySource;
use Salient\Contract\Sync\FilterPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncDefinitionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Concern\HasChainableMethods;
use Salient\Core\Concern\HasMutator;
use Salient\Core\Concern\HasReadableProperties;
use Salient\Core\Pipeline;
use Salient\Iterator\IterableIterator;
use Salient\Sync\Exception\FilterPolicyViolationException;
use Salient\Sync\Exception\SyncEntityNotFoundException;
use Salient\Sync\Reflection\ReflectionSyncEntity;
use Salient\Sync\Reflection\ReflectionSyncProvider;
use Salient\Sync\Support\SyncIntrospector;
use Salient\Sync\Support\SyncPipelineArgument;
use Salient\Utility\Reflect;
use Closure;
use LogicException;

/**
 * @template TEntity of SyncEntityInterface
 * @template TProvider of SyncProviderInterface
 *
 * @phpstan-type SyncOperationClosure (Closure(SyncContextInterface, int|string|null, mixed...): TEntity)|(Closure(SyncContextInterface, mixed...): iterable<TEntity>)|(Closure(SyncContextInterface, TEntity, mixed...): TEntity)|(Closure(SyncContextInterface, iterable<TEntity>, mixed...): iterable<TEntity>)
 * @phpstan-type OverrideClosure (Closure(static, OP::*, SyncContextInterface, int|string|null, mixed...): TEntity)|(Closure(static, OP::*, SyncContextInterface, mixed...): iterable<TEntity>)|(Closure(static, OP::*, SyncContextInterface, TEntity, mixed...): TEntity)|(Closure(static, OP::*, SyncContextInterface, iterable<TEntity>, mixed...): iterable<TEntity>)
 *
 * @property-read class-string<TEntity> $Entity The entity being serviced
 * @property-read TProvider $Provider The provider servicing the entity
 * @property-read array<OP::*> $Operations Supported sync operations
 * @property-read ListConformity::* $Conformity Conformity level of data returned by the provider for this entity
 * @property-read FilterPolicy::* $FilterPolicy Action to take when filters are not claimed by the provider
 * @property-read array<OP::*,Closure(SyncDefinitionInterface<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): (iterable<TEntity>|TEntity)> $Overrides Array that maps sync operations to closures that override other implementations
 * @phpstan-property-read array<OP::*,OverrideClosure> $Overrides
 * @property-read array<array-key,array-key|array-key[]>|null $KeyMap Array that maps keys to properties for entity data returned by the provider
 * @property-read int-mask-of<ArrayMapperFlag::*> $KeyMapFlags Array mapper flags used if a key map is provided
 * @property-read PipelineInterface<mixed[],TEntity,SyncPipelineArgument>|null $PipelineFromBackend Pipeline that maps provider data to a serialized entity, or `null` if mapping is not required
 * @property-read PipelineInterface<TEntity,mixed[],SyncPipelineArgument>|null $PipelineToBackend Pipeline that maps a serialized entity to provider data, or `null` if mapping is not required
 * @property-read bool $ReadFromList Perform READ operations by iterating over entities returned by READ_LIST
 * @property-read EntitySource::*|null $ReturnEntitiesFrom Source of entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 *
 * @implements SyncDefinitionInterface<TEntity,TProvider>
 */
abstract class AbstractSyncDefinition implements SyncDefinitionInterface, Chainable, Readable
{
    use HasChainableMethods;
    use HasMutator;
    use HasReadableProperties;

    /**
     * Get a closure to perform a sync operation on the entity
     *
     * This method is called if:
     *
     * - the operation is in {@see AbstractSyncDefinition::$Operations},
     * - there is no override for the operation, and
     * - the provider has not implemented the operation via a declared method
     *
     * @param OP::* $operation
     * @return (Closure(SyncContextInterface, mixed...): (iterable<TEntity>|TEntity))|null
     * @phpstan-return (
     *     $operation is OP::READ
     *     ? (Closure(SyncContextInterface, int|string|null, mixed...): TEntity)
     *     : (
     *         $operation is OP::READ_LIST
     *         ? (Closure(SyncContextInterface, mixed...): iterable<TEntity>)
     *         : (
     *             $operation is OP::CREATE|OP::UPDATE|OP::DELETE
     *             ? (Closure(SyncContextInterface, TEntity, mixed...): TEntity)
     *             : (Closure(SyncContextInterface, iterable<TEntity>, mixed...): iterable<TEntity>)
     *         )
     *     )
     * )|null
     */
    abstract protected function getClosure(int $operation): ?Closure;

    /**
     * The entity being serviced
     *
     * @var class-string<TEntity>
     */
    protected string $Entity;

    /**
     * The provider servicing the entity
     *
     * @var TProvider
     */
    protected SyncProviderInterface $Provider;

    /**
     * Supported sync operations
     *
     * @var array<OP::*>
     */
    protected array $Operations;

    /**
     * Conformity level of data returned by the provider for this entity
     *
     * Use {@see ListConformity::COMPLETE} or {@see ListConformity::PARTIAL}
     * wherever possible to improve performance.
     *
     * @var ListConformity::*
     */
    protected $Conformity;

    /**
     * Action to take when filters are not claimed by the provider
     *
     * To prevent a request for entities that meet one or more criteria
     * inadvertently reaching the backend as a request for a larger set of
     * entities--if not all of them--the default policy if there are unclaimed
     * filters is {@see FilterPolicy::THROW_EXCEPTION}.
     *
     * @see SyncContextInterface::withFilter()
     *
     * @var FilterPolicy::*
     */
    protected int $FilterPolicy;

    /**
     * Array that maps sync operations to closures that override other
     * implementations
     *
     * Two arguments are inserted before the operation's arguments:
     *
     * - The sync definition object
     * - The sync operation
     *
     * Operations implemented here are added to
     * {@see AbstractSyncDefinition::$Operations} automatically.
     *
     * @var array<OP::*,Closure(SyncDefinitionInterface<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): (iterable<TEntity>|TEntity)>
     * @phpstan-var array<OP::*,OverrideClosure>
     */
    protected array $Overrides = [];

    /**
     * Array that maps keys to properties for entity data returned by the
     * provider
     *
     * Providing a key map has the same effect as passing the following pipeline
     * to `$pipelineFromBackend`:
     *
     * ```php
     * <?php
     * Pipeline::create()->throughKeyMap($keyMap);
     * ```
     *
     * @var array<array-key,array-key|array-key[]>|null
     */
    protected ?array $KeyMap;

    /**
     * Array mapper flags used if a key map is provided
     *
     * @var int-mask-of<ArrayMapperFlag::*>
     */
    protected int $KeyMapFlags;

    /**
     * Pipeline that maps provider data to a serialized entity, or `null` if
     * mapping is not required
     *
     * @var PipelineInterface<mixed[],TEntity,SyncPipelineArgument>|null
     */
    protected ?PipelineInterface $PipelineFromBackend;

    /**
     * Pipeline that maps a serialized entity to provider data, or `null` if
     * mapping is not required
     *
     * @var PipelineInterface<TEntity,mixed[],SyncPipelineArgument>|null
     */
    protected ?PipelineInterface $PipelineToBackend;

    /**
     * Perform READ operations by iterating over entities returned by READ_LIST
     *
     * Useful with backends that don't provide an endpoint for retrieval of
     * individual entities.
     */
    protected bool $ReadFromList;

    /**
     * Source of entity data for the return value of a successful CREATE, UPDATE
     * or DELETE operation
     *
     * @var EntitySource::*|null
     */
    protected ?int $ReturnEntitiesFrom;

    /** @var ReflectionSyncEntity<TEntity> */
    protected ReflectionSyncEntity $EntityReflector;
    /** @var ReflectionSyncProvider<TProvider> */
    protected ReflectionSyncProvider $ProviderReflector;
    /** @var array<OP::*,SyncOperationClosure|null> */
    private array $Closures = [];
    /** @var static|null */
    private ?self $WithoutOverrides = null;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param array<OP::*> $operations
     * @param ListConformity::* $conformity
     * @param FilterPolicy::*|null $filterPolicy
     * @param array<int-mask-of<OP::*>,Closure(SyncDefinitionInterface<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): (iterable<TEntity>|TEntity)> $overrides
     * @phpstan-param array<int-mask-of<OP::*>,OverrideClosure> $overrides
     * @param array<array-key,array-key|array-key[]>|null $keyMap
     * @param int-mask-of<ArrayMapperFlag::*> $keyMapFlags
     * @param PipelineInterface<mixed[],TEntity,SyncPipelineArgument>|null $pipelineFromBackend
     * @param PipelineInterface<TEntity,mixed[],SyncPipelineArgument>|null $pipelineToBackend
     * @param EntitySource::*|null $returnEntitiesFrom
     */
    public function __construct(
        string $entity,
        SyncProviderInterface $provider,
        array $operations = [],
        $conformity = ListConformity::NONE,
        ?int $filterPolicy = null,
        array $overrides = [],
        ?array $keyMap = null,
        int $keyMapFlags = ArrayMapperFlag::ADD_UNMAPPED,
        ?PipelineInterface $pipelineFromBackend = null,
        ?PipelineInterface $pipelineToBackend = null,
        bool $readFromList = false,
        ?int $returnEntitiesFrom = null
    ) {
        $this->Entity = $entity;
        $this->Provider = $provider;
        $this->Conformity = $conformity;
        $this->FilterPolicy = $filterPolicy
            ?? $provider->getFilterPolicy() ?? FilterPolicy::THROW_EXCEPTION;
        $this->KeyMap = $keyMap;
        $this->KeyMapFlags = $keyMapFlags;
        $this->PipelineFromBackend = $pipelineFromBackend;
        $this->PipelineToBackend = $pipelineToBackend;
        $this->ReadFromList = $readFromList;
        $this->ReturnEntitiesFrom = $returnEntitiesFrom;

        /** @var list<int&OP::*> */
        $allOps = array_values(Reflect::getConstants(OP::class));

        // Expand $overrides into an entry per operation
        foreach ($overrides as $ops => $override) {
            foreach ($allOps as $op) {
                if (!($ops & $op)) {
                    continue;
                }
                if (array_key_exists($op, $this->Overrides)) {
                    throw new LogicException(sprintf(
                        'Too many overrides for SyncOperation::%s on %s: %s',
                        Reflect::getConstantName(OP::class, $op),
                        $entity,
                        get_class($provider),
                    ));
                }
                $this->Overrides[$op] = $override;
                $operations[] = $op;
            }
        }

        $this->Operations = array_intersect($allOps, $operations);
        $this->EntityReflector = new ReflectionSyncEntity($entity);
        $this->ProviderReflector = new ReflectionSyncProvider($provider);
    }

    /**
     * @internal
     */
    public function __clone()
    {
        $this->Closures = [];
        $this->WithoutOverrides = null;
    }

    /**
     * Get an instance with the given entity data conformity level
     *
     * @param ListConformity::* $conformity
     * @return static
     */
    final public function withConformity($conformity)
    {
        return $this->with('Conformity', $conformity);
    }

    /**
     * Get an instance with the given unclaimed filter policy
     *
     * @param FilterPolicy::* $policy
     * @return static
     */
    final public function withFilterPolicy(int $policy)
    {
        return $this->with('FilterPolicy', $policy);
    }

    /**
     * Get an instance that maps keys to the given properties for entity data
     * returned by the provider
     *
     * @param array<array-key,array-key|array-key[]>|null $map
     * @return static
     */
    final public function withKeyMap(?array $map)
    {
        return $this->with('KeyMap', $map);
    }

    /**
     * Get an instance where the given array mapper flags are used if a key map
     * is provided
     *
     * @param int-mask-of<ArrayMapperFlag::*> $flags
     * @return static
     */
    final public function withKeyMapFlags(int $flags)
    {
        return $this->with('KeyMapFlags', $flags);
    }

    /**
     * Get an instance that uses the given pipeline to map provider data to a
     * serialized entity
     *
     * @param PipelineInterface<mixed[],TEntity,SyncPipelineArgument>|null $pipeline
     * @return static
     */
    final public function withPipelineFromBackend(?PipelineInterface $pipeline)
    {
        return $this->with('PipelineFromBackend', $pipeline);
    }

    /**
     * Get an instance that uses the given pipeline to map a serialized entity
     * to provider data
     *
     * @param PipelineInterface<TEntity,mixed[],SyncPipelineArgument>|null $pipeline
     * @return static
     */
    final public function withPipelineToBackend(?PipelineInterface $pipeline)
    {
        return $this->with('PipelineToBackend', $pipeline);
    }

    /**
     * Get an instance that performs READ operations by iterating over entities
     * returned by READ_LIST
     *
     * @return static
     */
    final public function withReadFromList(bool $readFromList = true)
    {
        return $this->with('ReadFromList', $readFromList);
    }

    /**
     * Get an instance that uses the given entity data source for the return
     * value of a successful CREATE, UPDATE or DELETE operation
     *
     * @param EntitySource::*|null $source
     * @return static
     */
    final public function withReturnEntitiesFrom(?int $source)
    {
        return $this->with('ReturnEntitiesFrom', $source);
    }

    /**
     * @inheritDoc
     */
    final public function getOperationClosure(int $operation): ?Closure
    {
        // Return a previous result if possible
        if (array_key_exists($operation, $this->Closures)) {
            return $this->Closures[$operation];
        }

        // Overrides take precedence over everything else, including declared
        // methods
        if (array_key_exists($operation, $this->Overrides)) {
            /** @var SyncOperationClosure */
            $closure = fn(SyncContextInterface $ctx, ...$args) =>
                $this->Overrides[$operation](
                    $this,
                    $operation,
                    $this->getContextWithFilterCallback($operation, $ctx),
                    ...$args
                );
            return $this->Closures[$operation] = $closure;
        }

        // If a method has been declared for this operation, use it, even if
        // it's not in $this->Operations
        $closure = $this->ProviderReflector->getSyncOperationClosure(
            $operation,
            $this->EntityReflector,
            $this->Provider
        );

        if ($closure) {
            /** @var SyncOperationClosure */
            $closure = fn(SyncContextInterface $ctx, ...$args) =>
                $closure(
                    $this->getContextWithFilterCallback($operation, $ctx),
                    ...$args
                );
            return $this->Closures[$operation] = $closure;
        }

        if (
            $operation === OP::READ
            && $this->ReadFromList
            && ($closure = $this->getOperationClosure(OP::READ_LIST))
        ) {
            return $this->Closures[$operation] =
                function (SyncContextInterface $ctx, $id, ...$args) use ($closure) {
                    $entity = $this
                        ->getFluentIterator($closure($ctx, ...$args))
                        ->nextWithValue('Id', $id);
                    if ($entity === null) {
                        throw new SyncEntityNotFoundException($this->Provider, $this->Entity, $id);
                    }
                    return $entity;
                };
        }

        // Return null if the operation doesn't appear in $this->Operations
        if (!in_array($operation, $this->Operations, true)) {
            return $this->Closures[$operation] = null;
        }

        // Otherwise, get a closure from the subclass
        return $this->Closures[$operation] = $this->getClosure($operation);
    }

    /**
     * Ignoring overrides, get a closure to perform a sync operation on the
     * entity, throwing an exception if the operation is not supported
     *
     * @param OP::* $operation
     * @return (
     *     $operation is OP::READ
     *     ? (Closure(SyncContextInterface, int|string|null, mixed...): TEntity)
     *     : (
     *         $operation is OP::READ_LIST
     *         ? (Closure(SyncContextInterface, mixed...): iterable<TEntity>)
     *         : (
     *             $operation is OP::CREATE|OP::UPDATE|OP::DELETE
     *             ? (Closure(SyncContextInterface, TEntity, mixed...): TEntity)
     *             : (Closure(SyncContextInterface, iterable<TEntity>, mixed...): iterable<TEntity>)
     *         )
     *     )
     * )
     * @throws LogicException If the operation is not supported.
     */
    final public function getFallbackClosure(int $operation): Closure
    {
        $closure = ($this->WithoutOverrides ??= $this->with('Overrides', []))
            ->getOperationClosure($operation);

        if ($closure === null) {
            throw new LogicException(sprintf(
                'SyncOperation::%s not supported on %s',
                Reflect::getConstantName(OP::class, $operation),
                $this->Entity,
            ));
        }

        return $closure;
    }

    /**
     * Get an entity-to-data pipeline for the entity
     *
     * Before returning the pipeline:
     *
     * - a pipe that serializes any unserialized {@see SyncEntityInterface}
     *   instances is added via {@see PipelineInterface::through()}
     *
     * @return PipelineInterface<TEntity,mixed[],SyncPipelineArgument>
     */
    final protected function getPipelineToBackend(): PipelineInterface
    {
        /** @var PipelineInterface<TEntity,mixed[],SyncPipelineArgument> */
        $pipeline = $this->PipelineToBackend ?? Pipeline::create();

        /** @var PipelineInterface<TEntity,mixed[],SyncPipelineArgument> */
        $pipeline = $pipeline->through(
            fn($payload, Closure $next) =>
                $payload instanceof SyncEntityInterface
                    ? $next($payload->toArray())
                    : $next($payload)
        );

        return $pipeline;
    }

    /**
     * Get a data-to-entity pipeline for the entity
     *
     * Before returning the pipeline:
     *
     * - if the definition has a key map, it is applied via
     *   {@see PipelineInterface::throughKeyMap()}
     * - a closure to create instances of the entity from arrays returned by the
     *   pipeline is applied via {@see PipelineInterface::then()}
     *
     * @return PipelineInterface<mixed[],TEntity,SyncPipelineArgument>
     */
    final protected function getPipelineFromBackend(): PipelineInterface
    {
        /** @var PipelineInterface<mixed[],TEntity,SyncPipelineArgument> */
        $pipeline = $this->PipelineFromBackend ?? Pipeline::create();

        if ($this->KeyMap !== null) {
            $pipeline = $pipeline->throughKeyMap($this->KeyMap, $this->KeyMapFlags);
        }

        /** @var SyncPipelineArgument|null */
        $currentArg = null;
        /** @var SyncContextInterface|null */
        $ctx = null;

        return $pipeline
            ->then(function (
                array $data,
                PipelineInterface $pipeline,
                SyncPipelineArgument $arg
            ) use (&$ctx, &$closure, &$currentArg) {
                if (!$ctx || !$closure || $currentArg !== $arg) {
                    $ctx = $arg->Context->withConformity($this->Conformity);
                    $closure = in_array(
                        $this->Conformity,
                        [ListConformity::PARTIAL, ListConformity::COMPLETE]
                    )
                        ? SyncIntrospector::getService($ctx->getContainer(), $this->Entity)
                            ->getCreateSyncEntityFromSignatureClosure(array_keys($data))
                        : SyncIntrospector::getService($ctx->getContainer(), $this->Entity)
                            ->getCreateSyncEntityFromClosure();
                    $currentArg = $arg;
                }
                /** @var TEntity */
                $entity = $closure($data, $this->Provider, $ctx);

                return $entity;
            });
    }

    /**
     * Enforce the unclaimed filter policy
     *
     * @param OP::* $operation
     * @param array{}|null $empty
     *
     * @see AbstractSyncDefinition::$FilterPolicy
     */
    final protected function applyFilterPolicy(
        int $operation,
        SyncContextInterface $ctx,
        ?bool &$returnEmpty,
        &$empty
    ): void {
        $returnEmpty = false;

        if (
            $this->FilterPolicy === FilterPolicy::IGNORE
            || !($filter = $ctx->getFilter())
        ) {
            return;
        }

        switch ($this->FilterPolicy) {
            case FilterPolicy::THROW_EXCEPTION:
                throw new FilterPolicyViolationException($this->Provider, $this->Entity, $filter);

            case FilterPolicy::RETURN_EMPTY:
                $returnEmpty = true;
                $empty = SyncUtil::isListOperation($operation) ? [] : null;

                return;

            case FilterPolicy::FILTER:
                /** @todo Implement FilterPolicy::FILTER */
                break;
        }

        throw new LogicException(sprintf(
            'FilterPolicy invalid or not implemented: %s',
            $this->FilterPolicy,
        ));
    }

    /**
     * @param OP::* $operation
     */
    private function getContextWithFilterCallback(
        int $operation,
        SyncContextInterface $ctx
    ): SyncContextInterface {
        return $ctx->withFilterPolicyCallback(
            function (SyncContextInterface $ctx, ?bool &$returnEmpty, &$empty) use ($operation): void {
                $this->applyFilterPolicy($operation, $ctx, $returnEmpty, $empty);
            }
        );
    }

    /**
     * @param iterable<TEntity> $result
     * @return FluentIteratorInterface<array-key,TEntity>
     */
    private function getFluentIterator(iterable $result): FluentIteratorInterface
    {
        if (!$result instanceof FluentIteratorInterface) {
            return IterableIterator::fromValues($result);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public static function getReadableProperties(): array
    {
        return [
            'Entity',
            'Provider',
            'Operations',
            'Conformity',
            'FilterPolicy',
            'Overrides',
            'KeyMap',
            'KeyMapFlags',
            'PipelineFromBackend',
            'PipelineToBackend',
            'ReadFromList',
            'ReturnEntitiesFrom',
        ];
    }
}
