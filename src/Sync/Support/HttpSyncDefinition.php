<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\HttpRequestMethod;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Support\SyncOperation;
use UnexpectedValueException;

class HttpSyncDefinition extends SyncDefinition
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
     * @param array<int,Closure> $overrides
     */
    public function __construct(string $entity, HttpSyncProvider $provider, array $operations = [], $path = null, $query = null, int $conformity = ArrayKeyConformity::NONE, ?int $expiry = -1, array $methodMap = HttpSyncDefinition::DEFAULT_METHOD_MAP, array $overrides = [], ?IPipelineImmutable $dataToEntityPipeline = null, ?IPipelineImmutable $entityToDataPipeline = null)
    {
        parent::__construct($entity, $provider, $conformity, $dataToEntityPipeline, $entityToDataPipeline);

        // Combine overridden operations with $operations and remove invalid
        // values
        $this->Operations = array_intersect(
            SyncOperation::getAll(),
            array_merge(array_values($operations), array_keys($overrides))
        );
        $this->Path      = $path;
        $this->Query     = $query;
        $this->Expiry    = $expiry;
        $this->MethodMap = $methodMap;
        $this->Overrides = array_intersect_key($overrides, array_flip($this->Operations));
    }

    public function getSyncOperationClosure(int $operation): ?Closure
    {
        if (array_key_exists($operation, $this->Closures))
        {
            return $this->Closures[$operation];
        }

        // Overrides take precedence over everything else, including declared
        // methods
        if (array_key_exists($operation, $this->Overrides))
        {
            return $this->Closures[$operation] = $this->Overrides[$operation];
        }

        // If a method has been declared for this operation, use it, even if
        // it's not in $this->Operations
        if ($closure = $this->ProviderClosureBuilder->getSyncOperationClosure($operation, $this->EntityClosureBuilder, $this->Provider))
        {
            return $this->Closures[$operation] = $closure;
        }

        // Return null if the operation doesn't appear in $this->Operations, or
        // if no endpoint path has been provided
        if (!array_key_exists($operation, $this->Operations) || is_null($this->Path))
        {
            return $this->Closures[$operation] = null;
        }

        $toCurler  = $this->getCurlerOperationClosure($operation);
        $toBackend = $this->getPipelineToBackend();
        $toEntity  = $this->getPipelineToEntity();

        switch ($operation)
        {
            case SyncOperation::CREATE:
            case SyncOperation::UPDATE:
            case SyncOperation::DELETE:
                // $_args = [$operation, $ctx, $entity, ...$args]
                $endpointPipe = fn($payload, Closure $next, ...$_args) => $next(
                    $toCurler($this->getPath(...$_args), $this->getQuery(...$_args), $payload)
                );
                $closure = fn(SyncContext $ctx, SyncEntity $entity, ...$args): SyncEntity =>
                    ($toBackend->send($entity->toArray(), $operation, $ctx, $entity, ...$args)
                        ->through($endpointPipe)
                        ->then(fn($result) => $toEntity->send($result, $operation, $ctx, $entity, ...$args)->run())
                        ->run());
                break;

            case SyncOperation::READ:
                $closure = fn(SyncContext $ctx, $id, ...$args): SyncEntity => $toEntity->send(
                    $toCurler($this->getPath($operation, $ctx, $id, ...$args), $this->getQuery($operation, $ctx, $id, ...$args)),
                    $operation, $ctx, $id, ...$args
                )->run();
                break;

            case SyncOperation::CREATE_LIST:
            case SyncOperation::UPDATE_LIST:
            case SyncOperation::DELETE_LIST:
                // $_args = [$operation, $ctx, $_entity, ...$args]
                $endpointPipe = fn($payload, Closure $next, ...$_args) => $next(
                    $toCurler($this->getPath(...$_args), $this->getQuery(...$_args), $payload)
                );
                $_entity = null;
                $closure = function (SyncContext $ctx, iterable $entities, ...$args) use (&$_entity, $endpointPipe, $operation, $toBackend, $toEntity): iterable
                {
                    return ($toBackend->stream($entities, $operation, $ctx, $_entity, ...$args)
                        ->after(function (SyncEntity $entity) use (&$_entity) { $_entity = $entity; })
                        ->through($endpointPipe)
                        ->then(fn($result) => $toEntity->send($result, $operation, $ctx, $_entity, ...$args)->run())
                        ->start());
                };
                break;

            case SyncOperation::READ_LIST:
                $closure = fn(SyncContext $ctx, ...$args): iterable => $toEntity->stream(
                    $toCurler($this->getPath($operation, $ctx, ...$args), $this->getQuery($operation, $ctx, ...$args)),
                    $operation, $ctx, ...$args
                )->start();
                break;

            default:
                throw new UnexpectedValueException("Invalid SyncOperation: $operation");
        }

        return $this->Closures[$operation] = $closure;
    }

    private function getPath(int $operation, SyncContext $ctx, ...$args): string
    {
        if ($this->Path instanceof Closure)
        {
            return ($this->Path)($operation, $ctx, ...$args);
        }
        if ($operation === SyncOperation::READ && !is_null($args[0]))
        {
            return $this->Path . "/" . $args[0];
        }

        return $this->Path;
    }

    private function getQuery(int $operation, SyncContext $ctx, ...$args): ?array
    {
        if ($this->Query instanceof Closure)
        {
            return ($this->Query)($operation, $ctx, ...$args);
        }

        return $this->Query;
    }

    /**
     * @return Closure
     * ```php
     * fn(string $path, ?array $query, ?array $payload): array
     * ```
     */
    private function getCurlerOperationClosure(int $operation): Closure
    {
        switch ($this->MethodMap[$operation] ?? null)
        {
            case HttpRequestMethod::GET:
                return fn(string $path, ?array $query): array =>
                    $this->Provider->getCurler($path, $this->Expiry)->get($query);

            case HttpRequestMethod::POST:
                return fn(string $path, ?array $query, ?array $payload): array =>
                    $this->Provider->getCurler($path, $this->Expiry)->post($payload, $query);

            case HttpRequestMethod::PUT:
                return fn(string $path, ?array $query, ?array $payload): array =>
                    $this->Provider->getCurler($path, $this->Expiry)->put($payload, $query);

            case HttpRequestMethod::PATCH:
                return fn(string $path, ?array $query, ?array $payload): array =>
                    $this->Provider->getCurler($path, $this->Expiry)->patch($payload, $query);

            case HttpRequestMethod::DELETE:
                return fn(string $path, ?array $query, ?array $payload): array =>
                    $this->Provider->getCurler($path, $this->Expiry)->delete($payload, $query);

            default:
                throw new UnexpectedValueException("Invalid SyncOperation or method map: $operation");
        }
    }

}
