<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Curler\Curler;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\HttpRequestMethod;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncOperationNotImplementedException;
use Lkrms\Sync\Support\SyncOperation;
use RuntimeException;
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
class HttpSyncDefinition extends SyncDefinition implements HasBuilder
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
     * @var Closure|string|null
     */
    protected $Path;

    /**
     * @var Closure|array|null
     */
    protected $Query;

    /**
     * @var Closure|null
     */
    protected $HeadersCallback;

    /**
     * @var Closure|null
     */
    protected $PagerCallback;

    /**
     * @var Closure|HttpSyncDefinitionRequest|null
     */
    protected $Request;

    /**
     * Passed to the provider's getCurler() method
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
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param int[] $operations
     * @psalm-param array<SyncOperation::*> $operations
     * @param Closure|string|null $path Closure signature: `fn(int $operation, ISyncContext $ctx, ...$args): string`
     * @param Closure|array|null $query Closure signature: `fn(int $operation, ISyncContext $ctx, ...$args): ?array`
     * @param Closure|null $headersCallback Closure signature: `fn(Curler $curler, int $operation, ISyncContext $ctx, ...$args): ?CurlerHeaders`
     * @param Closure|null $pagerCallback Closure signature: `fn(Curler $curler, int $operation, ISyncContext $ctx, ...$args): ?ICurlerPager`
     * @param Closure|HttpSyncDefinitionRequest|null $request If set, `$path`,
     * `$query`, `$headersCallback` and `$pagerCallback` are ignored. Closure
     * signature: `fn(int $operation, ISyncContext $ctx, ...$args):
     * HttpSyncDefinitionRequest`
     * @psalm-param ArrayKeyConformity::* $conformity
     * @psalm-param SyncFilterPolicy::* $filterPolicy
     * @param array<int,Closure> $overrides
     * @psalm-param array<SyncOperation::*,Closure> $overrides
     * @psalm-param IPipeline<array,TEntity,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $dataToEntityPipeline
     * @psalm-param IPipeline<TEntity,array,array{0:int,1:ISyncContext,2?:int|string|ISyncEntity|ISyncEntity[]|null,...}>|null $entityToDataPipeline
     */
    public function __construct(string $entity, HttpSyncProvider $provider, array $operations = [], $path = null, $query = null, ?Closure $headersCallback = null, ?Closure $pagerCallback = null, $request = null, int $conformity = ArrayKeyConformity::NONE, int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION, ?int $expiry = -1, array $methodMap = HttpSyncDefinition::DEFAULT_METHOD_MAP, array $overrides = [], ?IPipeline $dataToEntityPipeline = null, ?IPipeline $entityToDataPipeline = null)
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

        $this->Path            = $path;
        $this->Query           = $query;
        $this->HeadersCallback = $headersCallback;
        $this->PagerCallback   = $pagerCallback;
        $this->Request         = $request;
        $this->Expiry          = $expiry;
        $this->MethodMap       = $methodMap;
    }

    /**
     * @psalm-param SyncOperation::* $operation
     */
    protected function getClosure(int $operation): ?Closure
    {
        // Return null if no endpoint path has been provided
        if (is_null($this->Request ?: $this->Path)) {
            return null;
        }

        $toCurler  = $this->getCurlerOperationClosure($operation);
        $toBackend = $this->getPipelineToBackend();
        $toEntity  = $this->getPipelineToEntity();

        switch ($operation) {
            case SyncOperation::CREATE:
            case SyncOperation::UPDATE:
            case SyncOperation::DELETE:
                $closure =
                    fn(ISyncContext $ctx, ISyncEntity $entity, ...$args): ISyncEntity =>
                        $toBackend->send($entity, $arg = [$operation, $ctx, $entity, ...$args])
                                  ->then(fn($data) =>
                                      $toCurler(...[...$this->getCurlerArgs(...$arg), $data]))
                                  ->runThrough($toEntity)
                                  ->withConformity($this->Conformity)
                                  ->run();
                break;

            case SyncOperation::READ:
                $closure =
                    fn(ISyncContext $ctx, $id, ...$args): ISyncEntity =>
                        $toEntity->send($toCurler(...$this->getCurlerArgs($operation, $ctx, $id, ...$args)),
                                        [$operation, $ctx, $id, ...$args])
                                 ->withConformity($this->Conformity)
                                 ->run();
                break;

            case SyncOperation::CREATE_LIST:
            case SyncOperation::UPDATE_LIST:
            case SyncOperation::DELETE_LIST:
                $entity  = null;
                $closure =
                    function (ISyncContext $ctx, iterable $entities, ...$args) use (&$entity, $operation, $toCurler, $toBackend, $toEntity): iterable {
                        return $toBackend->stream($entities, $arg = [$operation, $ctx, &$entity, ...$args])
                                         ->after(function (ISyncEntity $e) use (&$entity) { return $entity = $e; })
                                         ->then(fn($payload) =>
                                             $toCurler(...[...$this->getCurlerArgs(...$arg), $payload]))
                                         ->startThrough($toEntity)
                                         ->withConformity($this->Conformity)
                                         ->start();
                    };
                break;

            case SyncOperation::READ_LIST:
                $closure =
                    fn(ISyncContext $ctx, ...$args): iterable =>
                        $toEntity->stream($toCurler(...$this->getCurlerArgs($operation, $ctx, ...$args)),
                                          [$operation, $ctx, ...$args])
                                 ->withConformity($this->Conformity)
                                 ->start();
                break;

            default:
                throw new UnexpectedValueException("Invalid SyncOperation: $operation");
        }

        return $closure;
    }

    private function getCurlerArgs(int $operation, ISyncContext $ctx, ...$args): array
    {
        if (($request = $this->Request) instanceof Closure) {
            $request = $request($operation, $ctx, ...$args);
        }

        $args = $request instanceof HttpSyncDefinitionRequest ? [
            $ctx,
            $request->Path,
            $request->Query,
            $request->HeadersCallback,
            $request->PagerCallback,
        ] : [
            $ctx,
            $this->getPath($operation, $ctx, ...$args),
            $this->getQuery($operation, $ctx, ...$args),
            $this->HeadersCallback,
            $this->PagerCallback,
        ];

        // Wrap callbacks in closures to preserve $args
        foreach ([3, 4] as $i) {
            if (!$args[$i]) {
                continue;
            }
            $args[$i] = fn(Curler $curler) => ($args[$i])($curler, $operation, $ctx, ...$args);
        }

        return $args;
    }

    private function getPath(int $operation, ISyncContext $ctx, ...$args): string
    {
        if ($this->Path instanceof Closure) {
            return ($this->Path)($operation, $ctx, ...$args);
        }
        if (!($this->Query instanceof Closure) && $operation === SyncOperation::READ && !is_null($args[0])) {
            return $this->Path . '/' . $args[0];
        }

        return $this->Path;
    }

    private function getQuery(int $operation, ISyncContext $ctx, ...$args): ?array
    {
        if ($this->Query instanceof Closure) {
            return ($this->Query)($operation, $ctx, ...$args);
        }

        return $this->Query;
    }

    /**
     * Get a closure to perform a sync operation via HTTP
     *
     * @psalm-param SyncOperation::* $operation
     * @return Closure(ISyncContext, string, array|null, Closure|null, Closure|null, array|null)
     * ```php
     * fn(ISyncContext $ctx, string $path, ?array $query, ?Closure $headersCallback, ?Closure $pagerCallback, ?array $payload = null)
     * ```
     */
    private function getCurlerOperationClosure(int $operation): Closure
    {
        // Pagination with operations other than READ_LIST via GET or POST is
        // too risky to implement here, but providers can add their own support
        // for pagination with other operations and/or HTTP methods
        switch ([$operation, $this->MethodMap[$operation] ?? null]) {
            case [SyncOperation::READ_LIST, HttpRequestMethod::GET]:
                $runner = fn(Curler $curler, ?array $query) => $curler->Pager ? $curler->getP($query) : $curler->get($query);
                break;

            case [SyncOperation::READ_LIST, HttpRequestMethod::POST]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->Pager ? $curler->postP($payload, $query) : $curler->post($payload, $query);
                break;

            case [$operation, HttpRequestMethod::GET]:
                $runner = fn(Curler $curler, ?array $query) => $curler->get($query);
                break;

            case [$operation, HttpRequestMethod::POST]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->post($payload, $query);
                break;

            case [$operation, HttpRequestMethod::PUT]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->put($payload, $query);
                break;

            case [$operation, HttpRequestMethod::PATCH]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->patch($payload, $query);
                break;

            case [$operation, HttpRequestMethod::DELETE]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->delete($payload, $query);
                break;

            default:
                throw new UnexpectedValueException("Invalid SyncOperation or method map: $operation");
        }

        return fn(ISyncContext $ctx, string $path, ?array $query, ?Closure $headersCallback, ?Closure $pagerCallback, ?array $payload = null) =>
            $this->runCurlerOperation($runner, $operation, $ctx, $path, $query, $headersCallback, $pagerCallback, $payload);
    }

    /**
     * @param Closure $runner
     * ```php
     * fn(Curler $curler, ?array $query, ?array $payload)
     * ```
     * @psalm-param Closure(Curler, ?array, ?array) $runner
     * @psalm-param SyncOperation::* $operation
     */
    private function runCurlerOperation(Closure $runner, int $operation, ISyncContext $ctx, string $path, ?array $query, ?Closure $headersCallback, ?Closure $pagerCallback, ?array $payload)
    {
        $curler = $this->Provider->getCurler($path, $this->Expiry);
        if ($headersCallback && ($headers = $headersCallback($curler))) {
            $curler = $curler->withHeaders($headers);
        }
        if ($pagerCallback && ($pager = $pagerCallback($curler))) {
            $curler = $curler->withPager($pager);
        }

        // The callbacks above may have claimed values from the filter, so this
        // is the last and only place to enforce the unclaimed value policy
        if (SyncFilterPolicy::IGNORE === $this->FilterPolicy || !($filter = $ctx->getFilter())) {
            return $runner($curler, $query, $payload);
        }

        switch ($this->FilterPolicy) {
            case SyncFilterPolicy::THROW_EXCEPTION:
                throw new RuntimeException(get_class($this->Provider)
                    . " did not claim '" . implode("', '", array_keys($filter))
                    . "' from {$this->Entity} filter");

            case SyncFilterPolicy::RETURN_EMPTY:
                return SyncOperation::isList($operation) ? [] : null;

            case SyncFilterPolicy::FILTER_LOCALLY:
                throw new RuntimeException('SyncFilterPolicy::FILTER_LOCALLY is not implemented yet');
        }

        throw new UnexpectedValueException("Invalid SyncFilterPolicy: {$this->FilterPolicy}");
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
