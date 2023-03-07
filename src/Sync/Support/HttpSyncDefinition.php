<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Curler;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\HttpRequestMethod;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncOperationNotImplementedException;
use Lkrms\Sync\Support\SyncOperation;
use UnexpectedValueException;

/**
 * Provides direct access to an HttpSyncProvider's implementation of sync
 * operations for an entity
 *
 * Providers can use {@see HttpSyncDefinition} instead of hand-coded sync
 * operations to service HTTP backends declaratively.
 *
 * For entities that should be serviced this way, override
 * {@see HttpSyncProvider::getHttpDefinition()} and return an
 * {@see HttpSyncDefinition} or {@see HttpSyncDefinitionBuilder} that describes
 * the relevant endpoints.
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
 * @extends SyncDefinition<TEntity,TProvider>
 */
final class HttpSyncDefinition extends SyncDefinition implements HasBuilder
{
    public const DEFAULT_METHOD_MAP = [
        SyncOperation::CREATE      => HttpRequestMethod::POST,
        SyncOperation::READ        => HttpRequestMethod::GET,
        SyncOperation::UPDATE      => HttpRequestMethod::PUT,
        SyncOperation::DELETE      => HttpRequestMethod::DELETE,
        SyncOperation::CREATE_LIST => HttpRequestMethod::POST,
        SyncOperation::READ_LIST   => HttpRequestMethod::GET,
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
     * return value of {@see HttpSyncProvider::getCurlerCacheExpiry()} is used.
     * If `0`, responses are cached indefinitely.
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
     * @var array<int,string>
     */
    protected $MethodMap;

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
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param int[] $operations
     * @psalm-param array<SyncOperation::*> $operations
     * @psalm-param ArrayKeyConformity::* $conformity
     * @psalm-param SyncFilterPolicy::* $filterPolicy
     * @param array<int,Closure> $overrides
     * @psalm-param array<SyncOperation::*,Closure> $overrides
     * @psalm-param IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $dataToEntityPipeline
     * @psalm-param IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $entityToDataPipeline
     * @param mixed[]|null $query
     * @param (callable(HttpSyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $callback
     * @param array<int,string> $methodMap
     */
    public function __construct(string $entity, HttpSyncProvider $provider, array $operations = [], ?string $path = null, ?array $query = null, ?ICurlerHeaders $headers = null, ?ICurlerPager $pager = null, ?callable $callback = null, int $conformity = ArrayKeyConformity::NONE, int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION, ?int $expiry = -1, array $methodMap = HttpSyncDefinition::DEFAULT_METHOD_MAP, array $overrides = [], ?IPipeline $dataToEntityPipeline = null, ?IPipeline $entityToDataPipeline = null)
    {
        parent::__construct(
            $entity,
            $provider,
            $operations,
            $conformity,
            $filterPolicy,
            $overrides,
            $dataToEntityPipeline,
            $entityToDataPipeline
        );

        $this->Path      = $path;
        $this->Query     = $query;
        $this->Headers   = $headers;
        $this->Pager     = $pager;
        $this->Callback  = $callback;
        $this->Expiry    = $expiry;
        $this->MethodMap = $methodMap;
    }

    /**
     * Set the path to the provider endpoint servicing the entity
     *
     * @return $this
     * @see HttpSyncDefinition::$Path
     */
    public function withPath(?string $path)
    {
        $clone       = clone $this;
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
        $clone        = clone $this;
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
        $clone          = clone $this;
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
        $clone        = clone $this;
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
        $clone         = clone $this;
        $clone->Expiry = $expiry;

        return $clone;
    }

    protected function getClosure(int $operation): ?Closure
    {
        // Return null if no endpoint path has been provided
        if (is_null($this->Callback ?: $this->Path)) {
            return null;
        }

        $httpClosure = $this->getHttpOperationClosure($operation);
        $httpRunner  =
            fn(ISyncContext $ctx, ...$args) =>
                $this->runHttpOperation($httpClosure, $operation, $ctx, ...$args);

        switch ($operation) {
            case SyncOperation::CREATE:
            case SyncOperation::UPDATE:
            case SyncOperation::DELETE:
                return
                    fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity =>
                        $this->getPipelineToBackend()
                             ->send($entity, [$operation, $ctx, $entity, ...$args])
                             ->then(fn($data) => ($httpRunner)($ctx, $data, ...$args))
                             ->runThrough($this->getPipelineToEntity())
                             ->withConformity($this->Conformity)
                             ->run();

            case SyncOperation::READ:
                return
                    fn(ISyncContext $ctx, $id, ...$args): ISyncEntity =>
                        $this->getPipelineToEntity()
                             ->send(($httpRunner)($ctx, $id, ...$args), [$operation, $ctx, $id, ...$args])
                             ->withConformity($this->Conformity)
                             ->run();

            case SyncOperation::CREATE_LIST:
            case SyncOperation::UPDATE_LIST:
            case SyncOperation::DELETE_LIST:
                $entity = null;

                return
                    function (ISyncContext $ctx, iterable $entities, ...$args) use (&$entity, $operation, $httpRunner): iterable {
                        return $this->getPipelineToBackend()
                                    ->stream($entities, [$operation, $ctx, &$entity, ...$args])
                                    ->after(function (ISyncEntity $e) use (&$entity) { return $entity = $e; })
                                    ->then(fn($data) => ($httpRunner)($ctx, $data, ...$args))
                                    ->startThrough($this->getPipelineToEntity())
                                    ->withConformity($this->Conformity)
                                    ->start();
                    };

            case SyncOperation::READ_LIST:
                return
                    fn(ISyncContext $ctx, ...$args): iterable =>
                        $this->getPipelineToEntity()
                             ->stream(($httpRunner)($ctx, ...$args), [$operation, $ctx, ...$args])
                             ->withConformity($this->Conformity)
                             ->start();
        }

        throw new UnexpectedValueException("Invalid SyncOperation: $operation");
    }

    /**
     * Get a closure to perform a sync operation via HTTP
     *
     * @psalm-param SyncOperation::* $operation
     * @psalm-return Closure(Curler, mixed[]|null, mixed[]|null=): mixed[]
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
     * @psalm-param (Closure(Curler, mixed[]|null, mixed[]|null=): mixed[]) $httpClosure
     * @psalm-param SyncOperation::* $operation
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

        /** @var Curler $curler */
        $curler = $this->Provider->getCurler($def->Path, $def->Expiry);
        if ($def->Headers) {
            $curler = $curler->withHeaders($def->Headers);
        }
        if ($def->Pager) {
            $curler = $curler->withPager($def->Pager);
        }

        $this->applyFilterPolicy($operation, $ctx, $returnEmpty, $empty);
        if ($returnEmpty) {
            return $empty;
        }

        return ($httpClosure)($curler, $def->Query, $args[0] ?? null);
    }

    /**
     * Use a fluent interface to create a new HttpSyncDefinition object
     *
     */
    public static function build(?IContainer $container = null): HttpSyncDefinitionBuilder
    {
        return new HttpSyncDefinitionBuilder($container);
    }

    /**
     * @param HttpSyncDefinitionBuilder|HttpSyncDefinition|null $object
     */
    public static function resolve($object): HttpSyncDefinition
    {
        return HttpSyncDefinitionBuilder::resolve($object);
    }
}
