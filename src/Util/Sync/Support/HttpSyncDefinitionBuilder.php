<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Curler\Catalog\CurlerProperty;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Http\Catalog\HttpRequestMethod;
use Lkrms\Http\Contract\HttpHeadersInterface;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Lkrms\Sync\Catalog\FilterPolicy;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Salient\Core\Contract\PipelineInterface;
use Salient\Core\AbstractBuilder;
use Closure;

/**
 * A fluent HttpSyncDefinition factory
 *
 * @method $this operations(array<OP::*> $value) A list of supported sync operations
 * @method $this path(string[]|string|null $value) The path to the provider endpoint servicing the entity, e.g. "/v1/user" (see {@see HttpSyncDefinition::$Path})
 * @method $this query(mixed[]|null $value) Query parameters applied to the sync operation URL (see {@see HttpSyncDefinition::$Query})
 * @method $this headers(?HttpHeadersInterface $value) HTTP headers applied to the sync operation request (see {@see HttpSyncDefinition::$Headers})
 * @method $this pager(?ICurlerPager $value) The pagination handler for the endpoint servicing the entity (see {@see HttpSyncDefinition::$Pager})
 * @method $this callback((callable(HttpSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $value) A callback applied to the definition before every sync operation (see {@see HttpSyncDefinition::$Callback})
 * @method $this conformity(ArrayKeyConformity::* $value) The conformity level of data returned by the provider for this entity (see {@see SyncDefinition::$Conformity})
 * @method $this filterPolicy(FilterPolicy::*|null $value) The action to take when filters are unclaimed by the provider (see {@see SyncDefinition::$FilterPolicy})
 * @method $this expiry(?int $value) The time, in seconds, before responses from the provider expire (see {@see HttpSyncDefinition::$Expiry})
 * @method $this methodMap(array<OP::*,HttpRequestMethod::*> $value) An array that maps sync operations to HTTP request methods (see {@see HttpSyncDefinition::$MethodMap})
 * @method $this curlerProperties(array<CurlerProperty::*,mixed> $value) An array that maps Curler property names to values (see {@see HttpSyncDefinition::$CurlerProperties})
 * @method $this syncOneEntityPerRequest(bool $value = true) If true, perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on one entity per HTTP request (default: false)
 * @method $this overrides(array<int-mask-of<OP::*>,Closure(HttpSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): (iterable<TEntity>|TEntity)> $value) An array that maps sync operations to closures that override other implementations (see {@see SyncDefinition::$Overrides})
 * @method $this keyMap(array<array-key,array-key|array-key[]>|null $value) An array that maps provider (backend) keys to one or more entity keys (see {@see SyncDefinition::$KeyMap})
 * @method $this keyMapFlags(int-mask-of<ArrayMapperFlag::*> $value) Passed to the array mapper if `$keyMap` is provided
 * @method $this readFromReadList(bool $value = true) If true, perform READ operations by iterating over entities returned by READ_LIST (default: false; see {@see SyncDefinition::$ReadFromReadList})
 * @method $this returnEntitiesFrom(SyncEntitySource::*|null $value) Where to acquire entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of HttpSyncProvider
 *
 * @extends AbstractBuilder<HttpSyncDefinition<TEntity,TProvider>>
 *
 * @generated
 */
final class HttpSyncDefinitionBuilder extends AbstractBuilder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return HttpSyncDefinition::class;
    }

    /**
     * The ISyncEntity being serviced
     *
     * @template T of ISyncEntity
     *
     * @param class-string<T> $value
     * @return $this<T,TProvider>
     */
    public function entity(string $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * The ISyncProvider servicing the entity
     *
     * @template T of HttpSyncProvider
     *
     * @param T $value
     * @return $this<TEntity,T>
     */
    public function provider(HttpSyncProvider $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required
     *
     * @template T of ISyncEntity
     *
     * @param PipelineInterface<mixed[],T,array{0:OP::*,1:ISyncContext,2?:int|string|T|T[]|null,...}>|null $value
     * @return $this<T,TProvider>
     */
    public function pipelineFromBackend(?PipelineInterface $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required
     *
     * @template T of ISyncEntity
     *
     * @param PipelineInterface<T,mixed[],array{0:OP::*,1:ISyncContext,2?:int|string|T|T[]|null,...}>|null $value
     * @return $this<T,TProvider>
     */
    public function pipelineToBackend(?PipelineInterface $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }
}
