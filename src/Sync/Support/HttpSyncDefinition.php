<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Curler;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\HttpRequestMethod;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncInvalidEntitySourceException;
use Lkrms\Sync\Exception\SyncOperationNotImplementedException;
use Lkrms\Utility\Env;
use UnexpectedValueException;

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
 * @property-read string|null $Path The path to the provider endpoint servicing the entity, e.g. "/v1/user"
 * @property-read mixed[]|null $Query Query parameters applied to the sync operation URL
 * @property-read ICurlerHeaders|null $Headers HTTP headers applied to the sync operation request
 * @property-read ICurlerPager|null $Pager The pagination handler for the endpoint servicing the entity
 * @property-read int|null $Expiry The time, in seconds, before responses from the provider expire
 * @property-read array<SyncOperation::*,string> $MethodMap An array that maps sync operations to HTTP request methods
 * @property-read bool $SyncOneEntityPerRequest If true, perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on one entity per HTTP request
 * @property-read (callable(HttpSyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $Callback A callback applied to the definition before every sync operation
 *
 * @extends SyncDefinition<TEntity,TProvider>
 */
final class HttpSyncDefinition extends SyncDefinition implements HasBuilder
{
    public const DEFAULT_METHOD_MAP = [
        SyncOperation::CREATE => HttpRequestMethod::POST,
        SyncOperation::READ => HttpRequestMethod::GET,
        SyncOperation::UPDATE => HttpRequestMethod::PUT,
        SyncOperation::DELETE => HttpRequestMethod::DELETE,
        SyncOperation::CREATE_LIST => HttpRequestMethod::POST,
        SyncOperation::READ_LIST => HttpRequestMethod::GET,
        SyncOperation::UPDATE_LIST => HttpRequestMethod::PUT,
        SyncOperation::DELETE_LIST => HttpRequestMethod::DELETE,
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
     * @var string|null
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
     * The default method map is {@see HttpSyncDefinition::DEFAULT_METHOD_MAP}.
     * It contains:
     *
     * ```php
     * [
     *   SyncOperation::CREATE      => HttpRequestMethod::POST,
     *   SyncOperation::READ        => HttpRequestMethod::GET,
     *   SyncOperation::UPDATE      => HttpRequestMethod::PUT,
     *   SyncOperation::DELETE      => HttpRequestMethod::DELETE,
     *   SyncOperation::CREATE_LIST => HttpRequestMethod::POST,
     *   SyncOperation::READ_LIST   => HttpRequestMethod::GET,
     *   SyncOperation::UPDATE_LIST => HttpRequestMethod::PUT,
     *   SyncOperation::DELETE_LIST => HttpRequestMethod::DELETE,
     * ]
     * ```
     *
     * @var array<SyncOperation::*,string>
     */
    protected $MethodMap;

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
     * @var (callable(HttpSyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null
     */
    protected $Callback;

    /**
     * @var mixed[]|null
     */
    private $Args;

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
     * @param mixed[]|null $query
     * @param (callable(HttpSyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $callback
     * @param array<SyncOperation::*,string> $methodMap
     */
    public function __construct(
        string $entity,
        HttpSyncProvider $provider,
        array $operations = [],
        ?string $path = null,
        ?array $query = null,
        ?ICurlerHeaders $headers = null,
        ?ICurlerPager $pager = null,
        ?callable $callback = null,
        int $conformity = ArrayKeyConformity::NONE,
        int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION,
        ?int $expiry = -1,
        array $methodMap = HttpSyncDefinition::DEFAULT_METHOD_MAP,
        bool $syncOneEntityPerRequest = false,
        array $overrides = [],
        ?IPipeline $pipelineFromBackend = null,
        ?IPipeline $pipelineToBackend = null,
        ?int $returnEntitiesFrom = SyncEntitySource::HTTP_WRITE
    ) {
        parent::__construct(
            $entity,
            $provider,
            $operations,
            $conformity,
            $filterPolicy,
            $overrides,
            $pipelineFromBackend,
            $pipelineToBackend,
            $returnEntitiesFrom
        );

        $this->Path = $path;
        $this->Query = $query;
        $this->Headers = $headers;
        $this->Pager = $pager;
        $this->Callback = $callback;
        $this->Expiry = $expiry;
        $this->MethodMap = $methodMap;
        $this->SyncOneEntityPerRequest = $syncOneEntityPerRequest;
    }

    /**
     * Set the path to the provider endpoint servicing the entity
     *
     * @return $this
     * @see HttpSyncDefinition::$Path
     */
    public function withPath(?string $path)
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

    protected function getClosure(int $operation): ?Closure
    {
        // Return null if no endpoint path has been provided
        if (is_null($this->Callback ?: $this->Path)) {
            return null;
        }
        $httpClosure =
            SyncOperation::isWrite($operation) && Env::dryRun()
                ? fn(Curler $curler, ?array $query, $payload = null) =>
                    is_array($payload)
                        ? $payload
                        : []
                : $this->getHttpOperationClosure($operation);
        $httpRunner =
            fn(ISyncContext $ctx, ...$args) =>
                $this->runHttpOperation($httpClosure, $operation, $ctx, ...$args);

        switch ($operation) {
            case SyncOperation::CREATE:
            case SyncOperation::UPDATE:
            case SyncOperation::DELETE:
                return
                    fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity =>
                        $this
                            ->getPipelineToBackend()
                            ->send($entity, [$operation, $ctx, $entity, ...$args])
                            ->then(fn($data) => $this->getRoundTripPayload(($httpRunner)($ctx, $data, ...$args), $entity, $operation))
                            ->runInto($this->getRoundTripPipeline($operation))
                            ->run();

            case SyncOperation::READ:
                return
                    fn(ISyncContext $ctx, $id, ...$args): ISyncEntity =>
                        $this
                            ->getPipelineFromBackend()
                            ->send(($httpRunner)($ctx, $id, ...$args), [$operation, $ctx, $id, ...$args])
                            ->withConformity($this->Conformity)
                            ->run();

            case SyncOperation::CREATE_LIST:
            case SyncOperation::UPDATE_LIST:
            case SyncOperation::DELETE_LIST:
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

            case SyncOperation::READ_LIST:
                return
                    fn(ISyncContext $ctx, ...$args): iterable =>
                        $this
                            ->getPipelineFromBackend()
                            ->stream(($httpRunner)($ctx, ...$args), [$operation, $ctx, ...$args])
                            ->withConformity($this->Conformity)
                            ->start();
        }

        throw new UnexpectedValueException("Invalid SyncOperation: $operation");
    }

    /**
     * Get a closure to perform a sync operation via HTTP
     *
     * @param int&SyncOperation::* $operation
     * @return Closure(Curler, mixed[]|null, mixed[]|null=): mixed[]
     */
    private function getHttpOperationClosure(int $operation): Closure
    {
        // Pagination with operations other than READ_LIST via GET or POST is
        // too risky to implement here, but providers can add their own support
        // for pagination with other operations and/or HTTP methods
        switch ([$operation, $this->MethodMap[$operation] ?? null]) {
            case [SyncOperation::READ_LIST, HttpRequestMethod::GET]:
                return fn(Curler $curler, ?array $query) => $curler->Pager ? $curler->getP($query) : $curler->get($query);

            case [SyncOperation::READ_LIST, HttpRequestMethod::POST]:
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

        throw new UnexpectedValueException("Invalid SyncOperation or method map: $operation");
    }

    /**
     * Run a sync operation closure prepared earlier
     *
     * @param (Closure(Curler, mixed[]|null, mixed[]|null=): mixed[]) $httpClosure
     * @param int&SyncOperation::* $operation
     * @param mixed ...$args
     * @return mixed[]
     */
    private function runHttpOperation(Closure $httpClosure, int $operation, ISyncContext $ctx, ...$args)
    {
        $def = $this;
        if ($operation === SyncOperation::READ &&
                $this->Path &&
                !$this->Callback &&
                !is_null($id = $args[0] ?? null)) {
            $def = $def->withPath($this->Path . '/' . $id);
        }
        $def = $def->Callback
            ? ($def->Callback)($def, $operation, $ctx, ...$args)
            : $def;

        if ($def->Args !== null) {
            $args = $def->Args;
        }

        $curler = $this->Provider->getCurler($def->Path, $def->Expiry, $def->Headers, $def->Pager);

        $this->applyFilterPolicy($operation, $ctx, $returnEmpty, $empty);
        if ($returnEmpty) {
            return $empty;
        }

        return ($httpClosure)($curler, $def->Query, $args[0] ?? null);
    }

    /**
     * Get a payload for the round trip pipeline
     *
     * @param mixed[] $response
     * @param TEntity[]|TEntity $requestPayload
     * @param int&SyncOperation::* $operation
     * @return mixed[]
     */
    private function getRoundTripPayload($response, $requestPayload, int $operation)
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
     * @param int&SyncOperation::* $operation
     * @return IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>
     */
    private function getRoundTripPipeline(int $operation): IPipeline
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
     * Use a fluent interface to create a new HttpSyncDefinition object
     *
     * @return HttpSyncDefinitionBuilder<ISyncEntity,HttpSyncProvider>
     */
    public static function build(?IContainer $container = null): HttpSyncDefinitionBuilder
    {
        return new HttpSyncDefinitionBuilder($container);
    }

    /**
     * @template T0 of ISyncEntity
     * @template T1 of HttpSyncProvider
     * @param HttpSyncDefinitionBuilder<T0,T1>|HttpSyncDefinition<T0,T1> $object
     * @return HttpSyncDefinition<T0,T1>
     */
    public static function resolve($object): HttpSyncDefinition
    {
        return HttpSyncDefinitionBuilder::resolve($object);
    }

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
            'SyncOneEntityPerRequest',
            'Callback',
        ];
    }
}
