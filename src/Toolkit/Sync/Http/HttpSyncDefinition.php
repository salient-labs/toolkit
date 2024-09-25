<?php declare(strict_types=1);

namespace Salient\Sync\Http;

use Salient\Contract\Core\Pipeline\EntityPipelineInterface;
use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Contract\Core\Pipeline\StreamPipelineInterface;
use Salient\Contract\Core\ArrayMapperFlag;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Core\ListConformity;
use Salient\Contract\Core\ProviderContextInterface;
use Salient\Contract\Curler\Exception\HttpErrorExceptionInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpRequestMethod;
use Salient\Contract\Sync\EntitySource;
use Salient\Contract\Sync\FilterPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\Concern\HasMutator;
use Salient\Core\Pipeline;
use Salient\Sync\Exception\SyncEntityNotFoundException;
use Salient\Sync\Exception\SyncInvalidContextException;
use Salient\Sync\Exception\SyncInvalidEntitySourceException;
use Salient\Sync\Exception\SyncOperationNotImplementedException;
use Salient\Sync\Support\SyncPipelineArgument;
use Salient\Sync\AbstractSyncDefinition;
use Salient\Sync\SyncUtil;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Closure;
use LogicException;
use UnexpectedValueException;

/**
 * Generates closures that use an HttpSyncProvider to perform sync operations on
 * an entity
 *
 * Override {@see HttpSyncProvider::buildHttpDefinition()} to service HTTP
 * endpoints declaratively via {@see HttpSyncDefinition} objects.
 *
 * If more than one implementation of a sync operation is available for an
 * entity, the order of precedence is as follows:
 *
 * 1. The callback in {@see AbstractSyncDefinition::$Overrides} for the
 *    operation
 * 2. The provider method declared for the operation, e.g.
 *    `Provider::getFaculties()` or `Provider::createUser()`
 * 3. The closure returned by {@see AbstractSyncDefinition::getClosure()} for
 *    the operation
 *
 * If no implementations are found, {@see SyncOperationNotImplementedException}
 * is thrown.
 *
 * @template TEntity of SyncEntityInterface
 * @template TProvider of HttpSyncProvider
 *
 * @phpstan-type OverrideClosure (Closure(static, OP::*, SyncContextInterface, int|string|null, mixed...): TEntity)|(Closure(static, OP::*, SyncContextInterface, mixed...): iterable<TEntity>)|(Closure(static, OP::*, SyncContextInterface, TEntity, mixed...): TEntity)|(Closure(static, OP::*, SyncContextInterface, iterable<TEntity>, mixed...): iterable<TEntity>)
 *
 * @property-read string[]|string|null $Path Path or paths to the endpoint servicing the entity, e.g. "/v1/user"
 * @property-read mixed[]|null $Query Query parameters applied to the sync operation URL
 * @property-read HttpHeadersInterface|null $Headers HTTP headers applied to the sync operation request
 * @property-read CurlerPagerInterface|null $Pager Pagination handler for the endpoint servicing the entity
 * @property-read bool $AlwaysPaginate Use the pager to process requests even if no pagination is required
 * @property-read int<-1,max>|null $Expiry Seconds before cached responses expire
 * @property-read array<OP::*,HttpRequestMethod::*> $MethodMap Array that maps sync operations to HTTP request methods
 * @property-read (callable(CurlerInterface, HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): CurlerInterface)|null $CurlerCallback Callback applied to the Curler instance created to perform each sync operation
 * @property-read bool $SyncOneEntityPerRequest Perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on one entity per HTTP request
 * @property-read (callable(HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $Callback Callback applied to the definition before each sync operation
 * @property-read mixed[]|null $Args Arguments passed to each sync operation
 *
 * @extends AbstractSyncDefinition<TEntity,TProvider>
 * @implements Buildable<HttpSyncDefinitionBuilder<TEntity,TProvider>>
 */
final class HttpSyncDefinition extends AbstractSyncDefinition implements Buildable
{
    /** @use HasBuilder<HttpSyncDefinitionBuilder<TEntity,TProvider>> */
    use HasBuilder;
    use HasMutator;

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
     * Path or paths to the endpoint servicing the entity, e.g. "/v1/user"
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
     * are taken from the {@see SyncContextInterface} object received by the
     * sync operation. The first matching value is used:
     *
     * - Values applied explicitly via
     *   {@see ProviderContextInterface::withValue()} or implicitly via
     *   {@see ProviderContextInterface::push()}
     * - Unclaimed filters passed to the operation via
     *   {@see SyncContextInterface::withFilter()}
     *
     * Names are normalised for comparison by converting them to snake_case and
     * removing any `_id` suffixes.
     *
     * If multiple paths are given, each is tried in turn until a path is found
     * where every parameter can be resolved from the context.
     *
     * Filters are only claimed when a path is fully resolved.
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
    protected ?array $Query;

    /**
     * HTTP headers applied to the sync operation request
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withHeaders()} or
     * {@see HttpSyncDefinition::$Callback}.
     */
    protected ?HttpHeadersInterface $Headers;

    /**
     * Pagination handler for the endpoint servicing the entity
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withPager()} or
     * {@see HttpSyncDefinition::$Callback}.
     */
    protected ?CurlerPagerInterface $Pager;

    /**
     * Use the pager to process requests even if no pagination is required
     */
    protected bool $AlwaysPaginate;

    /**
     * Seconds before cached responses expire
     *
     * - `null`: do not cache responses
     * - `0`: cache responses indefinitely
     * - `-1` (default): use the value returned by
     *   {@see HttpSyncProvider::getExpiry()}
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withExpiry()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * @var int<-1,max>|null
     */
    protected ?int $Expiry;

    /**
     * Array that maps sync operations to HTTP request methods
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withMethodMap()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * The default method map is {@see HttpSyncDefinition::DEFAULT_METHOD_MAP}.
     *
     * @var array<OP::*,HttpRequestMethod::*>
     */
    protected array $MethodMap;

    /**
     * Callback applied to the Curler instance created to perform each sync
     * operation
     *
     * May be set via {@see HttpSyncDefinition::__construct()},
     * {@see HttpSyncDefinition::withCurlerCallback()} or
     * {@see HttpSyncDefinition::$Callback}.
     *
     * @var (callable(CurlerInterface, HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): CurlerInterface)|null
     */
    protected $CurlerCallback;

    /**
     * Perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on one entity
     * per HTTP request
     */
    protected bool $SyncOneEntityPerRequest;

    /**
     * Callback applied to the definition before each sync operation
     *
     * The callback must return the {@see HttpSyncDefinition} it receives even
     * if no request- or context-specific changes are needed.
     *
     * @var (callable(HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null
     */
    protected $Callback;

    /**
     * Arguments passed to each sync operation
     *
     * @var mixed[]|null
     */
    protected ?array $Args;

    /**
     * @internal
     *
     * @param class-string<TEntity> $entity
     * @param TProvider $provider
     * @param array<OP::*> $operations
     * @param string[]|string|null $path
     * @param mixed[]|null $query
     * @param (callable(HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $callback
     * @param ListConformity::* $conformity
     * @param FilterPolicy::*|null $filterPolicy
     * @param int<-1,max>|null $expiry
     * @param array<OP::*,HttpRequestMethod::*> $methodMap
     * @param (callable(CurlerInterface, HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): CurlerInterface)|null $curlerCallback
     * @param array<int-mask-of<OP::*>,Closure(HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): (iterable<TEntity>|TEntity)> $overrides
     * @phpstan-param array<int-mask-of<OP::*>,OverrideClosure> $overrides
     * @param array<array-key,array-key|array-key[]>|null $keyMap
     * @param int-mask-of<ArrayMapperFlag::*> $keyMapFlags
     * @param PipelineInterface<mixed[],TEntity,SyncPipelineArgument>|null $pipelineFromBackend
     * @param PipelineInterface<TEntity,mixed[],SyncPipelineArgument>|null $pipelineToBackend
     * @param EntitySource::*|null $returnEntitiesFrom
     * @param mixed[]|null $args
     */
    public function __construct(
        string $entity,
        HttpSyncProvider $provider,
        array $operations = [],
        $path = null,
        ?array $query = null,
        ?HttpHeadersInterface $headers = null,
        ?CurlerPagerInterface $pager = null,
        bool $alwaysPaginate = false,
        ?callable $callback = null,
        $conformity = ListConformity::NONE,
        ?int $filterPolicy = null,
        ?int $expiry = -1,
        array $methodMap = HttpSyncDefinition::DEFAULT_METHOD_MAP,
        ?callable $curlerCallback = null,
        bool $syncOneEntityPerRequest = false,
        array $overrides = [],
        ?array $keyMap = null,
        int $keyMapFlags = ArrayMapperFlag::ADD_UNMAPPED,
        ?PipelineInterface $pipelineFromBackend = null,
        ?PipelineInterface $pipelineToBackend = null,
        bool $readFromList = false,
        ?int $returnEntitiesFrom = EntitySource::PROVIDER_OUTPUT,
        ?array $args = null
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
            $readFromList,
            $returnEntitiesFrom
        );

        $this->Path = $path;
        $this->Query = $query;
        $this->Headers = $headers;
        $this->Pager = $pager;
        $this->AlwaysPaginate = $pager && $alwaysPaginate;
        $this->Callback = $callback;
        $this->Expiry = $expiry;
        $this->MethodMap = $methodMap;
        $this->CurlerCallback = $curlerCallback;
        $this->SyncOneEntityPerRequest = $syncOneEntityPerRequest;
        $this->Args = $args === null ? null : array_values($args);
    }

    /**
     * Get an instance with the given endpoint path or paths, e.g. "/v1/user"
     *
     * @param string[]|string|null $path
     * @return static
     */
    public function withPath($path)
    {
        return $this->with('Path', $path);
    }

    /**
     * Get an instance that applies the given query parameters to the sync
     * operation URL
     *
     * @param mixed[]|null $query
     * @return static
     */
    public function withQuery(?array $query)
    {
        return $this->with('Query', $query);
    }

    /**
     * Get an instance that applies the given HTTP headers to sync operation
     * requests
     *
     * @return static
     */
    public function withHeaders(?HttpHeadersInterface $headers)
    {
        return $this->with('Headers', $headers);
    }

    /**
     * Get an instance with the given pagination handler
     *
     * @param bool $alwaysPaginate If `true`, the pager is used to process
     * requests even if no pagination is required.
     * @return static
     */
    public function withPager(?CurlerPagerInterface $pager, bool $alwaysPaginate = false)
    {
        return $this
            ->with('Pager', $pager)
            ->with('AlwaysPaginate', $pager && $alwaysPaginate);
    }

    /**
     * Get an instance where cached responses expire after the given number of
     * seconds
     *
     * @param int<-1,max>|null $expiry - `null`: do not cache responses
     * - `0`: cache responses indefinitely
     * - `-1` (default): use the value returned by
     *   {@see HttpSyncProvider::getExpiry()}
     * @return static
     */
    public function withExpiry(?int $expiry)
    {
        return $this->with('Expiry', $expiry);
    }

    /**
     * Get an instance that maps sync operations to the given HTTP request
     * methods
     *
     * @param array<OP::*,HttpRequestMethod::*> $methodMap
     * @return static
     */
    public function withMethodMap(array $methodMap)
    {
        return $this->with('MethodMap', $methodMap);
    }

    /**
     * Get an instance that applies the given callback to the Curler instance
     * created for each sync operation
     *
     * @param (callable(CurlerInterface, HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): CurlerInterface)|null $callback
     * @return static
     */
    public function withCurlerCallback(?callable $callback)
    {
        return $this->with('CurlerCallback', $callback);
    }

    /**
     * Get an instance that replaces the arguments passed to each sync operation
     *
     * @param mixed[]|null $args
     * @return static
     */
    public function withArgs(?array $args)
    {
        return $this->with('Args', $args === null ? null : array_values($args));
    }

    /**
     * @inheritDoc
     */
    protected function getClosure($operation): ?Closure
    {
        // Return null if no endpoint path has been provided
        if (
            ($this->Path === null || $this->Path === [])
            && $this->Callback === null
        ) {
            return null;
        }

        switch ($operation) {
            case OP::CREATE:
            case OP::UPDATE:
            case OP::DELETE:
                return function (
                    SyncContextInterface $ctx,
                    SyncEntityInterface $entity,
                    ...$args
                ) use ($operation): SyncEntityInterface {
                    $arg = new SyncPipelineArgument($operation, $ctx, $args, null, $entity);
                    /** @var PipelineInterface<mixed[],TEntity,SyncPipelineArgument> */
                    $roundTrip = $this->getRoundTripPipeline($operation);
                    /** @var EntityPipelineInterface<TEntity,mixed[],SyncPipelineArgument> */
                    $toBackend = $this
                        ->getPipelineToBackend()
                        ->send($entity, $arg);
                    /** @var TEntity $entity */
                    return $toBackend
                        ->then(fn($data) => $this->getRoundTripPayload(
                            $this->runHttpOperation($operation, $ctx, $data, ...$args),
                            $entity,
                            $operation,
                        ))
                        ->runInto($roundTrip)
                        ->withConformity($this->Conformity)
                        ->run();
                };

            case OP::READ:
                return function (
                    SyncContextInterface $ctx,
                    $id,
                    ...$args
                ) use ($operation): SyncEntityInterface {
                    $arg = new SyncPipelineArgument($operation, $ctx, $args, $id);
                    return $this
                        ->getPipelineFromBackend()
                        ->send(
                            $this->runHttpOperation($operation, $ctx, $id, ...$args),
                            $arg,
                        )
                        ->withConformity($this->Conformity)
                        ->run();
                };

            case OP::CREATE_LIST:
            case OP::UPDATE_LIST:
            case OP::DELETE_LIST:
                return function (
                    SyncContextInterface $ctx,
                    iterable $entities,
                    ...$args
                ) use ($operation): iterable {
                    /** @var TEntity */
                    $entity = null;
                    $arg = new SyncPipelineArgument($operation, $ctx, $args, null, $entity);
                    /** @var PipelineInterface<mixed[],TEntity,SyncPipelineArgument> */
                    $roundTrip = $this->getRoundTripPipeline($operation);
                    /** @var StreamPipelineInterface<TEntity,mixed[],SyncPipelineArgument> */
                    $toBackend = $this
                        ->getPipelineToBackend()
                        ->stream($entities, $arg);

                    if ($this->SyncOneEntityPerRequest) {
                        $payload = &$entity;
                        /** @var Closure(TEntity): TEntity */
                        $after = function ($currentPayload) use (&$entity) {
                            return $entity = $currentPayload;
                        };
                        /** @var Closure(mixed[]): mixed[] */
                        $then = function ($data) use ($operation, $ctx, $args, &$payload) {
                            /** @var TEntity $payload */
                            return $this->getRoundTripPayload(
                                $this->runHttpOperation($operation, $ctx, $data, ...$args),
                                $payload,
                                $operation,
                            );
                        };
                        $toBackend = $toBackend
                            ->after($after)
                            ->then($then);
                    } else {
                        $payload = [];
                        /** @var Closure(TEntity): TEntity */
                        $after = function ($currentPayload) use (&$entity, &$payload) {
                            return $payload[] = $entity = $currentPayload;
                        };
                        /** @var Closure(array<mixed[]>): array<mixed[]> */
                        $then = function ($data) use ($operation, $ctx, $args, &$payload) {
                            /** @var TEntity[] $payload */
                            return $this->getRoundTripPayload(
                                $this->runHttpOperation($operation, $ctx, $data, ...$args),
                                $payload,
                                $operation,
                            );
                        };
                        $toBackend = $toBackend
                            ->after($after)
                            ->collectThen($then);
                    }

                    return $toBackend
                        ->startInto($roundTrip)
                        ->withConformity($this->Conformity)
                        ->unlessIf(fn($entity) => $entity === null)
                        ->start();
                };

            case OP::READ_LIST:
                return function (
                    SyncContextInterface $ctx,
                    ...$args
                ) use ($operation): iterable {
                    /** @var iterable<mixed[]>) */
                    $payload = $this->runHttpOperation($operation, $ctx, ...$args);
                    $arg = new SyncPipelineArgument($operation, $ctx, $args);
                    return $this
                        ->getPipelineFromBackend()
                        ->stream($payload, $arg)
                        ->withConformity($this->Conformity)
                        ->unlessIf(fn($entity) => $entity === null)
                        ->start();
                };
        }

        // @codeCoverageIgnoreStart
        throw new LogicException(sprintf(
            'Invalid SyncOperation: %d',
            $operation,
        ));
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get a closure to perform a sync operation via HTTP
     *
     * @param OP::* $operation
     * @return Closure(CurlerInterface, mixed[]|null, mixed[]|null=): (iterable<mixed[]>|mixed[])
     */
    private function getHttpOperationClosure($operation): Closure
    {
        // In dry-run mode, return a no-op closure for write operations
        if (
            SyncUtil::isWriteOperation($operation)
            && Env::getDryRun()
        ) {
            /** @var Closure(CurlerInterface, mixed[]|null, mixed[]|null=): (iterable<mixed[]>|mixed[]) */
            return fn(CurlerInterface $curler, ?array $query, ?array $payload = null) =>
                $payload ?? [];
        }

        // Pagination with operations other than READ_LIST via GET or POST can't
        // be safely implemented here, but providers can support pagination with
        // other operations and/or HTTP methods via overrides
        switch ([$operation, $this->MethodMap[$operation] ?? null]) {
            case [OP::READ_LIST, HttpRequestMethod::GET]:
                /** @var Closure(CurlerInterface, mixed[]|null): iterable<mixed[]> */
                return fn(CurlerInterface $curler, ?array $query) =>
                    $curler->getPager()
                        ? $curler->getP($query)
                        : $curler->get($query);

            case [OP::READ_LIST, HttpRequestMethod::POST]:
                /** @var Closure(CurlerInterface, mixed[]|null, mixed[]|null=): iterable<mixed[]> */
                return fn(CurlerInterface $curler, ?array $query, ?array $payload = null) =>
                    $curler->getPager()
                        ? $curler->postP($payload, $query)
                        : $curler->post($payload, $query);

            case [$operation, HttpRequestMethod::GET]:
                /** @var Closure(CurlerInterface, mixed[]|null): (iterable<mixed[]>|mixed[]) */
                return fn(CurlerInterface $curler, ?array $query) =>
                    $curler->get($query);

            case [$operation, HttpRequestMethod::POST]:
                /** @var Closure(CurlerInterface, mixed[]|null, mixed[]|null=): (iterable<mixed[]>|mixed[]) */
                return fn(CurlerInterface $curler, ?array $query, ?array $payload = null) =>
                    $curler->post($payload, $query);

            case [$operation, HttpRequestMethod::PUT]:
                /** @var Closure(CurlerInterface, mixed[]|null, mixed[]|null=): (iterable<mixed[]>|mixed[]) */
                return fn(CurlerInterface $curler, ?array $query, ?array $payload = null) =>
                    $curler->put($payload, $query);

            case [$operation, HttpRequestMethod::PATCH]:
                /** @var Closure(CurlerInterface, mixed[]|null, mixed[]|null=): (iterable<mixed[]>|mixed[]) */
                return fn(CurlerInterface $curler, ?array $query, ?array $payload = null) =>
                    $curler->patch($payload, $query);

            case [$operation, HttpRequestMethod::DELETE]:
                /** @var Closure(CurlerInterface, mixed[]|null, mixed[]|null=): (iterable<mixed[]>|mixed[]) */
                return fn(CurlerInterface $curler, ?array $query, ?array $payload = null) =>
                    $curler->delete($payload, $query);
        }

        // @codeCoverageIgnoreStart
        throw new LogicException(sprintf(
            'Invalid SyncOperation or method map: %d',
            $operation,
        ));
        // @codeCoverageIgnoreEnd
    }

    /**
     * Use an HTTP operation closure to perform a sync operation
     *
     * @param OP::* $operation
     * @param mixed ...$args
     * @return iterable<mixed[]>|mixed[]
     */
    private function runHttpOperation($operation, SyncContextInterface $ctx, ...$args)
    {
        return (
            $this->Callback === null
                ? $this
                : ($this->Callback)($this, $operation, $ctx, ...$args)
        )->doRunHttpOperation($operation, $ctx, ...$args);
    }

    /**
     * @param OP::* $operation
     * @param mixed ...$args
     * @return iterable<mixed[]>|mixed[]
     */
    private function doRunHttpOperation($operation, SyncContextInterface $ctx, ...$args)
    {
        if ($this->Path === null || $this->Path === []) {
            throw new LogicException('Path required');
        }

        if ($this->Args !== null) {
            $args = $this->Args;
        }

        $id = $this->getIdFromArgs($operation, $args);

        $paths = (array) $this->Path;
        while ($paths) {
            $claim = [];
            $idApplied = false;
            $path = array_shift($paths);
            // Use this path if it doesn't have any named parameters
            if (!Regex::matchAll(
                '/:(?<name>[[:alpha:]_][[:alnum:]_]*+)/',
                $path,
                $matches,
                \PREG_SET_ORDER
            )) {
                break;
            }
            /** @var string[] */
            $matches = Arr::unique(Arr::pluck($matches, 'name'));
            foreach ($matches as $name) {
                if (
                    $id !== null
                    && Str::snake($name) === 'id'
                ) {
                    $idApplied = true;
                    $path = $this->applyParameterValue((string) $id, $name, $path);
                    continue;
                }

                $value = $ctx->getFilter($name, false);
                $isFilter = true;
                if ($value === null) {
                    $value = $ctx->getValue($name);
                    $isFilter = false;
                }

                if ($value === null) {
                    if ($paths) {
                        continue 2;
                    }
                    throw new SyncInvalidContextException(
                        sprintf("Unable to resolve '%s' in path '%s'", $name, $path)
                    );
                }

                if (is_array($value)) {
                    if ($paths) {
                        continue 2;
                    }
                    throw new SyncInvalidContextException(
                        sprintf("Cannot apply array to '%s' in path '%s'", $name, $path)
                    );
                }

                $path = $this->applyParameterValue((string) $value, $name, $path);
                if ($isFilter) {
                    $claim[] = $name;
                }
            }
            break;
        }

        if ($claim) {
            foreach ($claim as $name) {
                $ctx->claimFilter($name);
            }
        }

        // If an operation is being performed on a sync entity with a known ID
        // that hasn't been applied to the path, and no callback has been
        // provided, add the conventional '/:id' to the endpoint
        if (
            $id !== null
            && !$idApplied
            && $this->Callback === null
            && strpos($path, '?') === false
        ) {
            $path .= '/' . $this->filterParameterValue(
                (string) $id, 'id', "$path/:id"
            );
        }

        $curler = $this->Provider->getCurler(
            $path,
            $this->Expiry,
            $this->Headers,
            $this->Pager,
            $this->AlwaysPaginate,
        );

        if ($this->CurlerCallback) {
            $curler = ($this->CurlerCallback)($curler, $this, $operation, $ctx, ...$args);
        }

        $this->applyFilterPolicy($operation, $ctx, $returnEmpty, $empty);
        if ($returnEmpty) {
            return $empty;
        }

        $httpClosure = $this->getHttpOperationClosure($operation);
        $payload = isset($args[0]) && is_array($args[0])
            ? $args[0]
            : null;

        try {
            return $httpClosure($curler, $this->Query, $payload);
        } catch (HttpErrorExceptionInterface $ex) {
            // If a request to READ a known entity fails with 404 (Not Found) or
            // 410 (Gone), throw a `SyncEntityNotFoundException`
            if ($operation === OP::READ && $id !== null && (
                ($status = $ex->getResponse()->getStatusCode()) === 404
                || $status === 410
            )) {
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
        if (SyncUtil::isListOperation($operation)) {
            return null;
        }

        if ($operation === OP::READ) {
            $id = $args[0] ?? null;

            if ($id === null || is_int($id) || is_string($id)) {
                return $id;
            }

            return null;
        }

        $entity = $args[0] ?? null;

        if (!$entity instanceof SyncEntityInterface) {
            return null;
        }

        return $entity->getId();
    }

    private function applyParameterValue(string $value, string $name, string $path): string
    {
        $value = $this->filterParameterValue($value, $name, $path);
        return Regex::replace("/:{$name}(?![[:alnum:]_])/", $value, $path);
    }

    private function filterParameterValue(string $value, string $name, string $path): string
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
     * @template TPayload of TEntity[]|TEntity
     *
     * @param iterable<mixed[]>|mixed[] $response
     * @param TPayload $requestPayload
     * @param OP::* $operation
     * @return (TPayload is TEntity[] ? iterable<mixed[]> : mixed[])
     */
    private function getRoundTripPayload($response, $requestPayload, $operation)
    {
        switch ($this->ReturnEntitiesFrom) {
            case EntitySource::PROVIDER_OUTPUT:
                /** @var iterable<mixed[]>|mixed[] */
                return Env::getDryRun()
                    ? $requestPayload
                    : $response;

            case EntitySource::OPERATION_INPUT:
                /** @var iterable<mixed[]>|mixed[] */
                return $requestPayload;

            default:
                // @codeCoverageIgnoreStart
                throw new SyncInvalidEntitySourceException(
                    $this->Provider, $this->Entity, $operation, $this->ReturnEntitiesFrom
                );
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param OP::* $operation
     * @return PipelineInterface<mixed[],TEntity,SyncPipelineArgument>
     */
    private function getRoundTripPipeline($operation): PipelineInterface
    {
        switch ($this->ReturnEntitiesFrom) {
            case EntitySource::PROVIDER_OUTPUT:
                /** @var PipelineInterface<mixed[],TEntity,SyncPipelineArgument> */
                return Env::getDryRun()
                    ? Pipeline::create()
                    : $this->getPipelineFromBackend();

            case EntitySource::OPERATION_INPUT:
                /** @var PipelineInterface<mixed[],TEntity,SyncPipelineArgument> */
                return Pipeline::create();

            default:
                // @codeCoverageIgnoreStart
                throw new SyncInvalidEntitySourceException(
                    $this->Provider, $this->Entity, $operation, $this->ReturnEntitiesFrom
                );
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @inheritDoc
     */
    public static function getReadableProperties(): array
    {
        return [
            ...parent::getReadableProperties(),
            'Path',
            'Query',
            'Headers',
            'Pager',
            'AlwaysPaginate',
            'Expiry',
            'MethodMap',
            'CurlerCallback',
            'SyncOneEntityPerRequest',
            'Callback',
            'Args',
        ];
    }
}
