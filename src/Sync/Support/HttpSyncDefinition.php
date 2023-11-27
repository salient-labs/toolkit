<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\HasBuilder;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\IProviderContext;
use Lkrms\Contract\ProvidesBuilder;
use Lkrms\Curler\Catalog\CurlerProperty;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Exception\CurlerHttpErrorException;
use Lkrms\Curler\Curler;
use Lkrms\Exception\UnexpectedValueException;
use Lkrms\Http\Catalog\HttpRequestMethod;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Catalog\FilterPolicy;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncEntityNotFoundException;
use Lkrms\Sync\Exception\SyncInvalidContextException;
use Lkrms\Sync\Exception\SyncInvalidEntitySourceException;
use Lkrms\Sync\Exception\SyncOperationNotImplementedException;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\Pcre;
use Closure;
use LogicException;

/**
 * Provides direct access to an HttpSyncProvider's implementation of sync
 * operations for an entity
 *
 * Providers can use {@see HttpSyncDefinition} instead of hand-coded sync
 * operations to service HTTP backends declaratively.
 *
 * To service entities this way, override
 * {@see HttpSyncProvider::buildHttpDefinition()} and return an
 * {@see HttpSyncDefinitionBuilder} that describes the relevant endpoints.
 *
 * If more than one implementation of a sync operation is available for an
 * entity, the order of precedence is as follows:
 *
 * 1. The callback in {@see SyncDefinition::$Overrides} for the operation
 * 2. The provider method declared for the operation, e.g.
 *    `Provider::getFaculties()` or `Provider::createUser()`
 * 3. The closure returned by {@see SyncDefinition::getClosure()} for the
 *    operation
 *
 * If no implementations are found, {@see SyncOperationNotImplementedException}
 * is thrown.
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of HttpSyncProvider
 *
 * @property-read string[]|string|null $Path The path to the provider endpoint servicing the entity, e.g. "/v1/user"
 * @property-read mixed[]|null $Query Query parameters applied to the sync operation URL
 * @property-read ICurlerHeaders|null $Headers HTTP headers applied to the sync operation request
 * @property-read ICurlerPager|null $Pager The pagination handler for the endpoint servicing the entity
 * @property-read int|null $Expiry The time, in seconds, before responses from the provider expire
 * @property-read array<OP::*,HttpRequestMethod::*> $MethodMap An array that maps sync operations to HTTP request methods
 * @property-read array<CurlerProperty::*,mixed> $CurlerProperties An array that maps Curler property names to values
 * @property-read bool $SyncOneEntityPerRequest If true, perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on one entity per HTTP request
 * @property-read (callable(HttpSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $Callback A callback applied to the definition before every sync operation
 *
 * @extends SyncDefinition<TEntity,TProvider>
 * @implements ProvidesBuilder<HttpSyncDefinitionBuilder<TEntity,TProvider>>
 */
final class HttpSyncDefinition extends SyncDefinition implements ProvidesBuilder
{
    use HasBuilder;

    public const DEFAULT_METHOD_MAP = [
        OP::CREATE => HttpRequestMethod::POST,
        OP::READ => HttpRequestMethod::GET,
        OP::UPDATE => HttpRequestMethod::PUT,
        OP::DELETE => HttpRequestMethod::DELETE,
        OP::CREATE_LIST => HttpRequestMethod::POST,
        OP::READ_LIST => HttpRequestMethod::GET,
        OP::UPDATE_LIST => HttpRequestMethod::PUT,
        OP::DELETE_LIST => HttpRequestMethod::DELETE,
    ];

    /**
     * The path to the provider endpoint servicing the entity, e.g. "/v1/user"
     *
     * Relative to {@see HttpSyncProvider::getBaseUrl()}.
     *
     * Must be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withPath()} or
     * {@see HttpSyncDefinition::$Callback} before a sync operation can be
     * performed.
     *
     * Must not include the provider's base URL.
     *
     * Values for named parameters (e.g. `groupId` in `"/group/:groupId/users"`)
     * are taken from the {@see ISyncContext} object received by the sync
     * operation. The first matching value is used:
     *
     * - Values applied explicitly via {@see IProviderContext::withValue()} or
     *   implicitly via {@see IProviderContext::push()}
     * - Unclaimed filters passed to the operation via
     *   {@see ISyncContext::withArgs()}
     *
     * Names are normalised for comparison by converting them to snake_case and
     * removing any `_id` suffixes.
     *
     * If multiple paths are given, each is tried in turn until a path is found
     * where every parameter can be resolved from the context.
     *
     * Filters are not claimed until a path is fully resolved.
     *
     * @link https://developer.mozilla.org/en-US/docs/Web/API/URL_Pattern_API
     *
     * @var string[]|string|null
     */
    protected $Path;

    /**
     * Query parameters applied to the sync operation URL
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withQuery()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * @var mixed[]|null
     */
    protected $Query;

    /**
     * HTTP headers applied to the sync operation request
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withHeaders()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * @var ICurlerHeaders|null
     */
    protected $Headers;

    /**
     * The pagination handler for the endpoint servicing the entity
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withPager()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * @var ICurlerPager|null
     */
    protected $Pager;

    /**
     * The time, in seconds, before responses from the provider expire
     *
     * If `null` (the default), responses are not cached. If less than `0`, the
     * return value of {@see HttpSyncProvider::getExpiry()} is used. If `0`,
     * responses are cached indefinitely.
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withExpiry()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * @var int|null
     */
    protected $Expiry;

    /**
     * An array that maps sync operations to HTTP request methods
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withMethodMap()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * The default method map is {@see HttpSyncDefinition::DEFAULT_METHOD_MAP}.
     * It contains:
     *
     * ```php
     * <?php
     * [
     *   OP::CREATE => HttpRequestMethod::POST,
     *   OP::READ => HttpRequestMethod::GET,
     *   OP::UPDATE => HttpRequestMethod::PUT,
     *   OP::DELETE => HttpRequestMethod::DELETE,
     *   OP::CREATE_LIST => HttpRequestMethod::POST,
     *   OP::READ_LIST => HttpRequestMethod::GET,
     *   OP::UPDATE_LIST => HttpRequestMethod::PUT,
     *   OP::DELETE_LIST => HttpRequestMethod::DELETE,
     * ]
     * ```
     *
     * @var array<OP::*,HttpRequestMethod::*>
     */
    protected $MethodMap;

    /**
     * An array that maps Curler property names to values
     *
     * May be set via {@see HttpSyncDefinition::__construct()} or extended via
     * {@see HttpSyncDefinition::withCurlerProperties()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * @see Curler
     *
     * @var array<CurlerProperty::*,mixed>
     */
    protected $CurlerProperties;

    /**
     * If true, perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on
     * one entity per HTTP request
     *
     * @var bool
     */
    protected $SyncOneEntityPerRequest;

    /**
     * A callback applied to the definition before every sync operation
     *
     * The callback must return the {@see HttpSyncDefinition} it receives even
     * if no request- or context-specific changes are needed.
     *
     * @var (callable(HttpSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null
     */
    protected $Callback;

    /**
     * @var mixed[]|null
     */
    private $Args;

    /**
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param array<OP::*> $operations
     * @param string[]|string|null $path
     * @param ArrayKeyConformity::* $conformity
     * @param FilterPolicy::*|null $filterPolicy
     * @param array<int-mask-of<OP::*>,Closure(HttpSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): (iterable<TEntity>|TEntity)> $overrides
     * @param array<array-key,array-key|array-key[]>|null $keyMap
     * @param int-mask-of<ArrayMapperFlag::*> $keyMapFlags
     * @param IPipeline<mixed[],TEntity,array{0:OP::*,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineFromBackend
     * @param IPipeline<TEntity,mixed[],array{0:OP::*,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $pipelineToBackend
     * @param SyncEntitySource::*|null $returnEntitiesFrom
     * @param mixed[]|null $query
     * @param (callable(HttpSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $callback
     * @param array<OP::*,HttpRequestMethod::*> $methodMap
     * @param array<CurlerProperty::*,mixed> $curlerProperties
     */
    public function __construct(
        string $entity,
        HttpSyncProvider $provider,
        array $operations = [],
        $path = null,
        ?array $query = null,
        ?ICurlerHeaders $headers = null,
        ?ICurlerPager $pager = null,
        ?callable $callback = null,
        $conformity = ArrayKeyConformity::NONE,
        ?int $filterPolicy = null,
        ?int $expiry = -1,
        array $methodMap = HttpSyncDefinition::DEFAULT_METHOD_MAP,
        array $curlerProperties = [],
        bool $syncOneEntityPerRequest = false,
        array $overrides = [],
        ?array $keyMap = null,
        int $keyMapFlags = ArrayMapperFlag::ADD_UNMAPPED,
        ?IPipeline $pipelineFromBackend = null,
        ?IPipeline $pipelineToBackend = null,
        bool $readFromReadList = false,
        ?int $returnEntitiesFrom = SyncEntitySource::HTTP_WRITE
    ) {
        parent::__construct(
            $entity,
            $provider,
            $operations,
            $conformity,
            $filterPolicy,
            $overrides,
            $keyMap,
            $keyMapFlags,
            $pipelineFromBackend,
            $pipelineToBackend,
            $readFromReadList,
            $returnEntitiesFrom
        );

        $this->Path = $path;
        $this->Query = $query;
        $this->Headers = $headers;
        $this->Pager = $pager;
        $this->Callback = $callback;
        $this->Expiry = $expiry;
        $this->MethodMap = $methodMap;
        $this->CurlerProperties = $curlerProperties;
        $this->SyncOneEntityPerRequest = $syncOneEntityPerRequest;
    }

    /**
     * Set the path to the provider endpoint servicing the entity
     *
     * @param string[]|string|null $path
     * @return $this
     * @see HttpSyncDefinition::$Path
     */
    public function withPath($path)
    {
        $clone = clone $this;
        $clone->Path = $path;

        return $clone;
    }

    /**
     * Set the query parameters applied to the sync operation URL
     *
     * @param mixed[]|null $query
     * @return $this
     * @see HttpSyncDefinition::$Query
     */
    public function withQuery(?array $query)
    {
        $clone = clone $this;
        $clone->Query = $query;

        return $clone;
    }

    /**
     * Set the HTTP headers applied to the sync operation request
     *
     * @return $this
     * @see HttpSyncDefinition::$Headers
     */
    public function withHeaders(?ICurlerHeaders $headers)
    {
        $clone = clone $this;
        $clone->Headers = $headers;

        return $clone;
    }

    /**
     * Set the pagination handler for the endpoint servicing the entity
     *
     * @return $this
     * @see HttpSyncDefinition::$Pager
     */
    public function withPager(?ICurlerPager $pager)
    {
        $clone = clone $this;
        $clone->Pager = $pager;

        return $clone;
    }

    /**
     * Set the time, in seconds, before responses from the provider expire
     *
     * @return $this
     * @see HttpSyncDefinition::$Expiry
     */
    public function withExpiry(?int $expiry)
    {
        $clone = clone $this;
        $clone->Expiry = $expiry;

        return $clone;
    }

    /**
     * Replace the array that maps sync operations to HTTP request methods
     *
     * @param array<OP::*,HttpRequestMethod::*> $methodMap
     * @return $this
     */
    public function withMethodMap(array $methodMap)
    {
        $clone = clone $this;
        $clone->MethodMap = $methodMap;

        return $clone;
    }

    /**
     * Extend the array that maps Curler property names to values
     *
     * @param array<CurlerProperty::*,mixed> $properties
     * @return $this
     */
    public function withCurlerProperties(array $properties)
    {
        $clone = clone $this;
        $clone->CurlerProperties = array_merge(
            $clone->CurlerProperties, $properties
        );

        return $clone;
    }

    /**
     * Replace the arguments passed to the operation
     *
     * @param mixed ...$args
     * @return $this
     */
    public function withArgs(...$args)
    {
        $clone = clone $this;
        $clone->Args = $args;

        return $clone;
    }

    protected function getClosure($operation): ?Closure
    {
        // Return null if no endpoint path has been provided
        if ($this->Callback === null &&
                ($this->Path === null || $this->Path === [])) {
            return null;
        }

        $httpClosure =
            OP::isWrite($operation) && Env::dryRun()
                ? fn(Curler $curler, ?array $query, $payload = null) =>
                    is_array($payload)
                        ? $payload
                        : []
                : fn(Curler $curler, ?array $query, $payload = null) =>
                    $this->getHttpOperationClosure($operation)($curler, $query, $payload);
        $httpRunner =
            fn(ISyncContext $ctx, ...$args) =>
                $this->runHttpOperation($httpClosure, $operation, $ctx, ...$args);

        switch ($operation) {
            case OP::CREATE:
            case OP::UPDATE:
            case OP::DELETE:
                return
                    fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity =>
                        $this
                            ->getPipelineToBackend()
                            ->send($entity, [$operation, $ctx, $entity, ...$args])
                            ->then(fn($data) => $this->getRoundTripPayload(($httpRunner)($ctx, $data, ...$args), $entity, $operation))
                            ->runInto($this->getRoundTripPipeline($operation))
                            ->run();

            case OP::READ:
                return
                    fn(ISyncContext $ctx, $id, ...$args): ISyncEntity =>
                        $this
                            ->getPipelineFromBackend()
                            ->send(($httpRunner)($ctx, $id, ...$args), [$operation, $ctx, $id, ...$args])
                            ->withConformity($this->Conformity)
                            ->run();

            case OP::CREATE_LIST:
            case OP::UPDATE_LIST:
            case OP::DELETE_LIST:
                return
                    function (ISyncContext $ctx, iterable $entities, ...$args) use ($operation, $httpRunner): iterable {
                        $entity = null;
                        if ($this->SyncOneEntityPerRequest) {
                            $payload = &$entity;
                            $after = function (ISyncEntity $e) use (&$entity) { return $entity = $e; };
                        } else {
                            $payload = [];
                            $after = function (ISyncEntity $e) use (&$entity, &$payload) { return $payload[] = $entity = $e; };
                        }
                        $then = function ($data) use ($operation, $httpRunner, $ctx, $args, &$payload) {
                            return $this->getRoundTripPayload(($httpRunner)($ctx, $data, ...$args), $payload, $operation);
                        };

                        return $this
                            ->getPipelineToBackend()
                            ->stream($entities, [$operation, $ctx, &$entity, ...$args])
                            ->after($after)
                            ->if(
                                $this->SyncOneEntityPerRequest,
                                fn(IPipeline $p) => $p->then($then),
                                fn(IPipeline $p) => $p->collectThen($then)
                            )
                            ->startInto($this->getRoundTripPipeline($operation))
                            ->start();
                    };

            case OP::READ_LIST:
                return
                    fn(ISyncContext $ctx, ...$args): iterable =>
                        $this
                            ->getPipelineFromBackend()
                            ->stream(($httpRunner)($ctx, ...$args), [$operation, $ctx, ...$args])
                            ->withConformity($this->Conformity)
                            ->start();
        }

        throw new LogicException("Invalid SyncOperation: $operation");
    }

    /**
     * Get a closure to perform a sync operation via HTTP
     *
     * @param OP::* $operation
     * @return Closure(Curler, mixed[]|null, mixed[]|null=): mixed[]
     */
    private function getHttpOperationClosure($operation): Closure
    {
        // Pagination with operations other than READ_LIST via GET or POST is
        // too risky to implement here, but providers can add their own support
        // for pagination with other operations and/or HTTP methods
        switch ([$operation, $this->MethodMap[$operation] ?? null]) {
            case [OP::READ_LIST, HttpRequestMethod::GET]:
                return fn(Curler $curler, ?array $query) => $curler->Pager ? $curler->getP($query) : $curler->get($query);

            case [OP::READ_LIST, HttpRequestMethod::POST]:
                return fn(Curler $curler, ?array $query, ?array $payload = null) => $curler->Pager ? $curler->postP($payload, $query) : $curler->post($payload, $query);

            case [$operation, HttpRequestMethod::GET]:
                return fn(Curler $curler, ?array $query) => $curler->get($query);

            case [$operation, HttpRequestMethod::POST]:
                return fn(Curler $curler, ?array $query, ?array $payload = null) => $curler->post($payload, $query);

            case [$operation, HttpRequestMethod::PUT]:
                return fn(Curler $curler, ?array $query, ?array $payload = null) => $curler->put($payload, $query);

            case [$operation, HttpRequestMethod::PATCH]:
                return fn(Curler $curler, ?array $query, ?array $payload = null) => $curler->patch($payload, $query);

            case [$operation, HttpRequestMethod::DELETE]:
                return fn(Curler $curler, ?array $query, ?array $payload = null) => $curler->delete($payload, $query);
        }

        throw new LogicException("Invalid SyncOperation or method map: $operation");
    }

    /**
     * Run a sync operation closure prepared earlier
     *
     * @param (Closure(Curler, mixed[]|null, mixed[]|null=): mixed[]) $httpClosure
     * @param OP::* $operation
     * @param mixed ...$args
     * @return mixed[]
     */
    private function runHttpOperation(Closure $httpClosure, $operation, ISyncContext $ctx, ...$args)
    {
        $def =
            $this->Callback === null
                ? $this
                : ($this->Callback)($this, $operation, $ctx, ...$args);

        if ($def->Path === null || $def->Path === []) {
            throw new LogicException('Path required');
        }

        if ($def->Args !== null) {
            $args = $def->Args;
        }

        $id = $this->getIdFromArgs($operation, $args);

        $paths = (array) $def->Path;
        while ($paths) {
            $claim = [];
            $idApplied = false;
            $path = array_shift($paths);
            try {
                $path = Pcre::replaceCallback(
                    '/:(?<name>[[:alpha:]_][[:alnum:]_]*)/',
                    function (array $matches) use (
                        $operation,
                        $ctx,
                        $id,
                        &$claim,
                        &$idApplied,
                        $path
                    ): string {
                        $name = $matches['name'];
                        if ($id !== null &&
                                Convert::toSnakeCase($name) === 'id') {
                            $idApplied = true;
                            return $this->checkParameterValue(
                                (string) $id, $name, $path
                            );
                        }

                        $value = $ctx->getFilter($name);
                        if ($value === null) {
                            $value = $ctx->getValue($name);
                        } else {
                            $claim[$name] = true;
                        }

                        if ($value === null) {
                            throw new SyncInvalidContextException(
                                sprintf("Unable to resolve '%s' in path '%s'", $name, $path),
                                $ctx,
                                $this->Provider,
                                $this->Entity,
                                $operation,
                            );
                        }

                        return $this->checkParameterValue(
                            (string) $value, $name, $path
                        );
                    },
                    $path
                );
                break;
            } catch (SyncInvalidContextException $ex) {
                if (!$paths) {
                    throw $ex;
                }
            }
        }

        if ($claim) {
            foreach (array_keys($claim) as $name) {
                $ctx->claimFilter($name);
            }
        }

        // If an operation is being performed on a sync entity with a known ID
        // that hasn't been applied to the path, and no callback has been
        // provided, add the conventional '/:id' to the endpoint
        if ($id !== null &&
                !$idApplied &&
                $this->Callback === null &&
                strpos($path, '?') === false) {
            $path .= '/' . $this->checkParameterValue(
                (string) $id, 'id', "$path/:id"
            );
        }

        $curler = $this->Provider->getCurler($path, $def->Expiry, $def->Headers, $def->Pager);

        foreach ($def->CurlerProperties as $property => $value) {
            $curler = $curler->with($property, $value);
        }

        $def->applyFilterPolicy($operation, $ctx, $returnEmpty, $empty);
        if ($returnEmpty) {
            return $empty;
        }

        try {
            return $httpClosure->call($def, $curler, $def->Query, $args[0] ?? null);
        } catch (CurlerHttpErrorException $ex) {
            if ($operation === OP::READ &&
                    $id !== null &&
                    $ex->isNotFound(true)) {
                throw new SyncEntityNotFoundException(
                    $this->Provider,
                    $this->Entity,
                    $id,
                    $ex,
                );
            }
            throw $ex;
        }
    }

    /**
     * @param OP::* $operation
     * @param mixed[] $args
     * @return int|string|null
     */
    private function getIdFromArgs($operation, array $args)
    {
        if (OP::isList($operation)) {
            return null;
        }

        if ($operation === OP::READ) {
            return $args[0] ?? null;
        }

        $entity = $args[0] ?? null;

        if (!($entity instanceof ISyncEntity)) {
            return null;
        }

        return $entity->id();
    }

    private function checkParameterValue(string $value, string $name, string $path): string
    {
        if (strpos($value, '/') !== false) {
            throw new UnexpectedValueException(
                sprintf("Cannot apply value of '%s' to path '%s': %s", $name, $path, $value),
            );
        }
        return rawurlencode($value);
    }

    /**
     * Get a payload for the round trip pipeline
     *
     * @param mixed[] $response
     * @param TEntity[]|TEntity $requestPayload
     * @param OP::* $operation
     * @return mixed[]
     */
    private function getRoundTripPayload($response, $requestPayload, $operation)
    {
        switch ($this->ReturnEntitiesFrom) {
            case SyncEntitySource::HTTP_WRITE:
                return Env::dryRun()
                    ? $requestPayload
                    : $response;

            case SyncEntitySource::SYNC_OPERATION:
                return $requestPayload;

            default:
                throw new SyncInvalidEntitySourceException(
                    $this->Provider, $this->Entity, $operation, $this->ReturnEntitiesFrom
                );
        }
    }

    /**
     * @param OP::* $operation
     * @return IPipeline<mixed[],TEntity,array{0:OP::*,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>
     */
    private function getRoundTripPipeline($operation): IPipeline
    {
        switch ($this->ReturnEntitiesFrom) {
            case SyncEntitySource::SYNC_OPERATION:
                return new Pipeline();

            case SyncEntitySource::HTTP_READ:
            case SyncEntitySource::HTTP_WRITE:
                return $this->getPipelineFromBackend();

            default:
                throw new SyncInvalidEntitySourceException(
                    $this->Provider, $this->Entity, $operation, $this->ReturnEntitiesFrom
                );
        }
    }

    /**
     * @inheritDoc
     */
    public static function getReadable(): array
    {
        return [
            ...parent::getReadable(),
            'Path',
            'Query',
            'Headers',
            'Pager',
            'Expiry',
            'MethodMap',
            'CurlerProperties',
            'SyncOneEntityPerRequest',
            'Callback',
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getBuilder(): string
    {
        return HttpSyncDefinitionBuilder::class;
    }
}
