<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IPipeline;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Closure;

/**
 * Creates HttpSyncDefinition objects via a fluent interface
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of HttpSyncProvider
 *
 * @method $this operations(array<SyncOperation::*> $value) A list of supported sync operations
 * @method $this path(?string $value) The path to the provider endpoint servicing the entity, e.g. "/v1/user" (see {@see HttpSyncDefinition::$Path})
 * @method $this query(mixed[]|null $value) Query parameters applied to the sync operation URL (see {@see HttpSyncDefinition::$Query})
 * @method $this headers(?ICurlerHeaders $value) HTTP headers applied to the sync operation request (see {@see HttpSyncDefinition::$Headers})
 * @method $this pager(?ICurlerPager $value) The pagination handler for the endpoint servicing the entity (see {@see HttpSyncDefinition::$Pager})
 * @method $this callback((callable(HttpSyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $value) A callback applied to the definition before every sync operation (see {@see HttpSyncDefinition::$Callback})
 * @method $this conformity(ArrayKeyConformity::* $value) The conformity level of data returned by the provider for this entity (see {@see SyncDefinition::$Conformity})
 * @method $this filterPolicy(SyncFilterPolicy::* $value) The action to take when filters are unclaimed by the provider (see {@see SyncDefinition::$FilterPolicy})
 * @method $this expiry(?int $value) The time, in seconds, before responses from the provider expire (see {@see HttpSyncDefinition::$Expiry})
 * @method $this methodMap(array<SyncOperation::*,string> $value) An array that maps sync operations to HTTP request methods (see {@see HttpSyncDefinition::$MethodMap})
 * @method $this syncOneEntityPerRequest(bool $value = true) If true, perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on one entity per HTTP request (default: false)
 * @method $this overrides(array<SyncOperation::*,Closure(ISyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): mixed> $value) An array that maps sync operations to closures that override any other implementations (see {@see SyncDefinition::$Overrides})
 * @method $this pipelineFromBackend(IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $value) A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required
 * @method $this pipelineToBackend(IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $value) A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required
 * @method $this returnEntitiesFrom(SyncEntitySource::*|null $value) Where to acquire entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 *
 * @uses HttpSyncDefinition
 *
 * @extends Builder<HttpSyncDefinition<TEntity,TProvider>>
 */
final class HttpSyncDefinitionBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return HttpSyncDefinition::class;
    }

    /**
     * @internal
     */
    protected static function getTerminators(): array
    {
        return [];
    }

    /**
     * The ISyncEntity being serviced
     *
     * @template T of ISyncEntity
     * @param class-string<T> $value
     * @return $this<T,TProvider>
     */
    public function entity(string $value)
    {
        return $this->getWithValue(__FUNCTION__, $value);
    }

    /**
     * The ISyncProvider servicing the entity
     *
     * @template T of HttpSyncProvider
     * @param T $value
     * @return $this<TEntity,T>
     */
    public function provider(HttpSyncProvider $value)
    {
        return $this->getWithValue(__FUNCTION__, $value);
    }
}
