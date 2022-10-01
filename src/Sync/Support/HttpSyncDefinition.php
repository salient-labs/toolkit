<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\HttpRequestMethod;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Sync\SyncEntity;
use Lkrms\Sync\SyncOperation;
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
     * @var string|null
     */
    protected $Path;

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
     * @param array<int,Closure> $overrides
     */
    public function __construct(string $entity, HttpSyncProvider $provider, array $operations = [], ?string $path = null, int $conformity = ArrayKeyConformity::NONE, ?int $expiry = -1, array $methodMap = HttpSyncDefinition::DEFAULT_METHOD_MAP, array $overrides = [], ?IPipelineImmutable $dataToEntityPipeline = null, ?IPipelineImmutable $entityToDataPipeline = null)
    {
        parent::__construct($entity, $provider, $conformity, $dataToEntityPipeline, $entityToDataPipeline);

        // Combine overridden operations with $operations and remove invalid
        // values
        $this->Operations = array_intersect(
            SyncOperation::getAll(),
            array_merge(array_values($operations), array_keys($overrides))
        );
        $this->Path      = $path;
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
                $closure = fn(SyncContext $ctx, SyncEntity $entity, ...$args): SyncEntity =>
                    ($toBackend->send($entity->toArray(), $operation, $ctx, $entity, ...$args)
                        ->through(fn($payload, Closure $next, ...$args) => $next($toCurler($this->getPath($operation, $ctx, $entity, ...$args), $payload)))
                        ->then(fn($result) => $toEntity->send($result, $operation, $ctx, $entity, ...$args)->run())
                        ->run());
                break;

            case SyncOperation::READ:
                $closure = fn(SyncContext $ctx, ?int $id = null, ...$args): SyncEntity =>
                    $toEntity->send($toCurler($this->getPath($operation, $ctx, $id, ...$args)), $operation, $ctx, $id, ...$args)->run();
                    break;

            case SyncOperation::CREATE_LIST:
            case SyncOperation::UPDATE_LIST:
            case SyncOperation::DELETE_LIST:
                $_entity = null;
                $closure = function (SyncContext $ctx, iterable $entities, ...$args) use (&$_entity, $operation, $toCurler, $toBackend, $toEntity): iterable
                {
                    return ($toBackend->stream($entities, $operation, $ctx, $_entity, ...$args)
                        ->after(function (SyncEntity $entity) use (&$_entity) { $_entity = $entity; })
                        ->through(fn($payload, Closure $next, ...$args) => $next($toCurler($this->getPath($operation, $ctx, $_entity, ...$args), $payload)))
                        ->then(fn($result) => $toEntity->send($result, $operation, $ctx, $_entity, ...$args)->run())
                        ->start());
                };
                break;

            case SyncOperation::READ_LIST:
                $closure = fn(SyncContext $ctx, ...$args): iterable =>
                    $toEntity->stream($toCurler($this->getPath($operation, $ctx, ...$args)), $operation, $ctx, ...$args)->start();
                    break;

            default:
                throw new UnexpectedValueException("Invalid SyncOperation: $operation");
        }

        return $this->Closures[$operation] = $closure;
    }

    private function getPath(int $operation, SyncContext $ctx, ...$args): string
    {
        if ($operation === SyncOperation::READ && !is_null($args[0]))
        {
            return $this->Path . "/" . $args[0];
        }

        return $this->Path;
    }

    private function getCurlerOperationClosure(int $operation): Closure
    {
        switch ($this->MethodMap[$operation] ?? null)
        {
            case HttpRequestMethod::GET:
                return fn(string $path, ...$args) =>
                    $this->Provider->getCurler($path, $this->Expiry)->getJson(...$args);

            case HttpRequestMethod::POST:
                return fn(string $path, ...$args) =>
                    $this->Provider->getCurler($path, $this->Expiry)->postJson(...$args);

            case HttpRequestMethod::PUT:
                return fn(string $path, ...$args) =>
                    $this->Provider->getCurler($path, $this->Expiry)->putJson(...$args);

            case HttpRequestMethod::PATCH:
                return fn(string $path, ...$args) =>
                    $this->Provider->getCurler($path, $this->Expiry)->patchJson(...$args);

            case HttpRequestMethod::DELETE:
                return fn(string $path, ...$args) =>
                    $this->Provider->getCurler($path, $this->Expiry)->deleteJson(...$args);

            default:
                throw new UnexpectedValueException("Invalid SyncOperation or method map: $operation");
        }
    }

}
