<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Curler\Curler;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\HttpRequestMethod;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Support\SyncOperation;
use RuntimeException;
use UnexpectedValueException;

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
     * @var HttpSyncProvider
     */
    protected $Provider;

    /**
     * @var int[]
     */
    protected $Operations;

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
     * @var int|null
     */
    protected $Expiry;

    /**
     * @var array<int,string>
     */
    protected $MethodMap;

    /**
     * @var array<int,Closure>
     */
    protected $Overrides;

    /**
     * @var array<int,Closure>
     */
    private $Closures = [];

    /**
     * @param int[] $operations
     * @param Closure|string|null $path Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): string`
     * @param Closure|array|null $query Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): ?array`
     * @param Closure|null $headersCallback Closure signature: `fn(Curler $curler, int $operation, SyncContext $ctx, ...$args): ?CurlerHeaders`
     * @param Closure|null $pagerCallback Closure signature: `fn(Curler $curler, int $operation, SyncContext $ctx, ...$args): ?ICurlerPager`
     * @param Closure|HttpSyncDefinitionRequest|null $request If set, `$path`, `$query`, `$headersCallback` and `$pagerCallback` are ignored. Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): HttpSyncDefinitionRequest`
     * @param array<int,Closure> $overrides
     */
    public function __construct(string $entity, HttpSyncProvider $provider, array $operations = [], $path = null, $query = null, ?Closure $headersCallback = null, ?Closure $pagerCallback = null, $request = null, int $conformity = ArrayKeyConformity::NONE, int $filterPolicy = SyncFilterPolicy::THROW_EXCEPTION, ?int $expiry = -1, array $methodMap = HttpSyncDefinition::DEFAULT_METHOD_MAP, array $overrides = [], ?IPipelineImmutable $dataToEntityPipeline = null, ?IPipelineImmutable $entityToDataPipeline = null)
    {
        parent::__construct($entity, $provider, $conformity, $filterPolicy, $dataToEntityPipeline, $entityToDataPipeline);

        // Combine overridden operations with $operations and remove invalid
        // values
        $this->Operations = array_intersect(
            SyncOperation::getAll(),
            array_merge(array_values($operations), array_keys($overrides))
        );
        $this->Path            = $path;
        $this->Query           = $query;
        $this->HeadersCallback = $headersCallback;
        $this->PagerCallback   = $pagerCallback;
        $this->Request         = $request;
        $this->Expiry          = $expiry;
        $this->MethodMap       = $methodMap;
        $this->Overrides       = array_intersect_key($overrides, array_flip($this->Operations));
    }

    public function getSyncOperationClosure(int $operation): ?Closure
    {
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
        if ($closure = $this->ProviderIntrospector->getDeclaredSyncOperationClosure($operation, $this->EntityIntrospector, $this->Provider)) {
            return $this->Closures[$operation] = $closure;
        }

        // Return null if the operation doesn't appear in $this->Operations, or
        // if no endpoint path has been provided
        if (!array_key_exists($operation, $this->Operations) || is_null($this->Request ?: $this->Path)) {
            return $this->Closures[$operation] = null;
        }

        $toCurler  = $this->getCurlerOperationClosure($operation);
        $toBackend = $this->getPipelineToBackend();
        $toEntity  = $this->getPipelineToEntity();

        switch ($operation) {
            case SyncOperation::CREATE:
            case SyncOperation::UPDATE:
            case SyncOperation::DELETE:
                // $_args = [$operation, $ctx, $entity, ...$args]
                $endpointPipe = fn($payload, Closure $next, IPipeline $pipeline, ...$_args) => $next(
                    $toCurler(...[...$this->getCurlerArgs(...$_args), $payload])
                );
                $closure = fn(SyncContext $ctx, SyncEntity $entity, ...$args): SyncEntity =>
                    ($toBackend->send($entity->toArray(), $operation, $ctx, $entity, ...$args)
                        ->through($endpointPipe)
                        ->then(fn($result) =>
                            $toEntity->send($result, $operation, $ctx, $entity, ...$args)
                            ->withConformity($this->Conformity)
                            ->run())
                        ->run());
                break;

            case SyncOperation::READ:
                $closure = fn(SyncContext $ctx, $id, ...$args): SyncEntity => $toEntity->send(
                    $toCurler(...$this->getCurlerArgs($operation, $ctx, $id, ...$args)),
                    $operation, $ctx, $id, ...$args
                )->withConformity($this->Conformity)->run();
                break;

            case SyncOperation::CREATE_LIST:
            case SyncOperation::UPDATE_LIST:
            case SyncOperation::DELETE_LIST:
                // $_args = [$operation, $ctx, $_entity, ...$args]
                $endpointPipe = fn($payload, Closure $next, IPipeline $pipeline, ...$_args) => $next(
                    $toCurler(...[...$this->getCurlerArgs(...$_args), $payload])
                );
                $_entity = null;
                $closure = function (SyncContext $ctx, iterable $entities, ...$args) use (&$_entity, $endpointPipe, $operation, $toBackend, $toEntity): iterable {
                    return ($toBackend->stream($entities, $operation, $ctx, $_entity, ...$args)
                        ->after(function (SyncEntity $entity) use (&$_entity) {$_entity = $entity;})
                        ->through($endpointPipe)
                        ->then(fn($result) =>
                            $toEntity->send($result, $operation, $ctx, $_entity, ...$args)
                            ->withConformity($this->Conformity)
                            ->run())
                        ->start());
                };
                break;

            case SyncOperation::READ_LIST:
                $closure = fn(SyncContext $ctx, ...$args): iterable => $toEntity->stream(
                    $toCurler(...$this->getCurlerArgs($operation, $ctx, ...$args)),
                    $operation, $ctx, ...$args
                )->withConformity($this->Conformity)->start();
                break;

            default:
                throw new UnexpectedValueException("Invalid SyncOperation: $operation");
        }

        return $this->Closures[$operation] = $closure;
    }

    private function getCurlerArgs(int $operation, SyncContext $ctx, ...$args): array
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

    private function getPath(int $operation, SyncContext $ctx, ...$args): string
    {
        if ($this->Path instanceof Closure) {
            return ($this->Path)($operation, $ctx, ...$args);
        }
        if (!($this->Query instanceof Closure) && $operation === SyncOperation::READ && !is_null($args[0])) {
            return $this->Path . '/' . $args[0];
        }

        return $this->Path;
    }

    private function getQuery(int $operation, SyncContext $ctx, ...$args): ?array
    {
        if ($this->Query instanceof Closure) {
            return ($this->Query)($operation, $ctx, ...$args);
        }

        return $this->Query;
    }

    /**
     * @return Closure
     * ```php
     * fn(SyncContext $ctx, string $path, ?array $query, ?Closure $headersCallback, ?array $payload)
     * ```
     */
    private function getCurlerOperationClosure(int $operation): Closure
    {
        // Pagination with operations other than READ_LIST via GET or POST is
        // too risky to implement here, but providers can add their own support
        // for pagination with other operations and/or HTTP methods
        switch ([$operation, $this->MethodMap[$operation] ?? null]) {
            case[SyncOperation::READ_LIST, HttpRequestMethod::GET]:
                $runner = fn(Curler $curler, ?array $query) => $curler->Pager ? $curler->getP($query) : $curler->get($query);
                break;

            case[SyncOperation::READ_LIST, HttpRequestMethod::POST]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->Pager ? $curler->postP($payload, $query) : $curler->post($payload, $query);
                break;

            case[$operation, HttpRequestMethod::GET]:
                $runner = fn(Curler $curler, ?array $query) => $curler->get($query);
                break;

            case[$operation, HttpRequestMethod::POST]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->post($payload, $query);
                break;

            case[$operation, HttpRequestMethod::PUT]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->put($payload, $query);
                break;

            case[$operation, HttpRequestMethod::PATCH]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->patch($payload, $query);
                break;

            case[$operation, HttpRequestMethod::DELETE]:
                $runner = fn(Curler $curler, ?array $query, ?array $payload) => $curler->delete($payload, $query);
                break;

            default:
                throw new UnexpectedValueException("Invalid SyncOperation or method map: $operation");
        }

        return fn(SyncContext $ctx, string $path, ?array $query, ?Closure $headersCallback, ?Closure $pagerCallback, ?array $payload = null) =>
            $this->runCurlerOperation($runner, $operation, $ctx, $path, $query, $headersCallback, $pagerCallback, $payload);
    }

    private function runCurlerOperation(Closure $runner, int $operation, SyncContext $ctx, string $path, ?array $query, ?Closure $headers, ?Closure $pager, ?array $payload)
    {
        $curler = $this->Provider->getCurler($path, $this->Expiry);
        if ($headers && ($headers = $headers($curler))) {
            $curler->replaceHeaders($headers);
        }
        if ($pager && ($pager = $pager($curler))) {
            $curler->replacePager($pager);
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
