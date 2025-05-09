<?php declare(strict_types=1);

namespace Salient\Sync\Http;

use Salient\Contract\Core\Pipeline\ArrayMapperInterface;
use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\HeadersInterface;
use Salient\Contract\Sync\EntitySource;
use Salient\Contract\Sync\FilterPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Core\Builder;
use Salient\Sync\Support\SyncPipelineArgument;
use Salient\Sync\AbstractSyncDefinition;
use Closure;

/**
 * @method $this operations(array<OP::*> $value) Supported sync operations
 * @method $this path(string[]|string|null $value) Path or paths to the endpoint servicing the entity, e.g. "/v1/user" (see {@see HttpSyncDefinition::$Path})
 * @method $this query(mixed[]|null $value) Query parameters applied to the sync operation URL (see {@see HttpSyncDefinition::$Query})
 * @method $this headers(?HeadersInterface $value) HTTP headers applied to the sync operation request (see {@see HttpSyncDefinition::$Headers})
 * @method $this pager(?CurlerPagerInterface $value) Pagination handler for the endpoint servicing the entity (see {@see HttpSyncDefinition::$Pager})
 * @method $this alwaysPaginate(bool $value = true) Use the pager to process requests even if no pagination is required (default: false)
 * @method $this callback((callable(HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): HttpSyncDefinition<TEntity,TProvider>)|null $value) Callback applied to the definition before each sync operation (see {@see HttpSyncDefinition::$Callback})
 * @method $this conformity(HttpSyncDefinition::CONFORMITY_* $value) Conformity level of data returned by the provider for this entity (see {@see AbstractSyncDefinition::$Conformity})
 * @method $this filterPolicy(FilterPolicy::*|null $value) Action to take when filters are not claimed by the provider (see {@see AbstractSyncDefinition::$FilterPolicy})
 * @method $this expiry(int<-1,max>|null $value) Seconds before cached responses expire (see {@see HttpSyncDefinition::$Expiry})
 * @method $this methodMap(array<OP::*,HttpSyncDefinition::METHOD_*> $value) Array that maps sync operations to HTTP request methods (see {@see HttpSyncDefinition::$MethodMap})
 * @method $this curlerCallback((callable(CurlerInterface, HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): CurlerInterface)|null $value) Callback applied to the Curler instance created to perform each sync operation (see {@see HttpSyncDefinition::$CurlerCallback})
 * @method $this syncOneEntityPerRequest(bool $value = true) Perform CREATE_LIST, UPDATE_LIST and DELETE_LIST operations on one entity per HTTP request (default: false)
 * @method $this overrides(array<int-mask-of<OP::*>,Closure(HttpSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): (iterable<array-key,TEntity>|TEntity)> $value) Array that maps sync operations to closures that override other implementations (see {@see AbstractSyncDefinition::$Overrides})
 * @method $this keyMap(array<array-key,array-key|array-key[]>|null $value) Array that maps keys to properties for entity data returned by the provider (see {@see AbstractSyncDefinition::$KeyMap})
 * @method $this keyMapFlags(int-mask-of<ArrayMapperInterface::REMOVE_NULL|ArrayMapperInterface::ADD_UNMAPPED|ArrayMapperInterface::ADD_MISSING|ArrayMapperInterface::REQUIRE_MAPPED> $value) Array mapper flags used if a key map is provided
 * @method $this readFromList(bool $value = true) Perform READ operations by iterating over entities returned by READ_LIST (default: false; see {@see AbstractSyncDefinition::$ReadFromList})
 * @method $this returnEntitiesFrom(EntitySource::*|null $value) Source of entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 * @method $this args(mixed[]|null $value) Arguments passed to each sync operation
 *
 * @template TEntity of SyncEntityInterface
 * @template TProvider of HttpSyncProvider
 *
 * @extends Builder<HttpSyncDefinition<TEntity,TProvider>>
 *
 * @generated
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
     * The entity being serviced
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $value
     * @return static<T,TProvider>
     */
    public function entity(string $value)
    {
        /** @var static<T,TProvider> */
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * The provider servicing the entity
     *
     * @template T of HttpSyncProvider
     *
     * @param T $value
     * @return static<TEntity,T>
     */
    public function provider(HttpSyncProvider $value)
    {
        /** @var static<TEntity,T> */
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * Pipeline that maps provider data to a serialized entity, or `null` if mapping is not required
     *
     * @template T of SyncEntityInterface
     *
     * @param PipelineInterface<mixed[],T,SyncPipelineArgument>|null $value
     * @return static<T,TProvider>
     */
    public function pipelineFromBackend(?PipelineInterface $value)
    {
        /** @var static<T,TProvider> */
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * Pipeline that maps a serialized entity to provider data, or `null` if mapping is not required
     *
     * @template T of SyncEntityInterface
     *
     * @param PipelineInterface<T,mixed[],SyncPipelineArgument>|null $value
     * @return static<T,TProvider>
     */
    public function pipelineToBackend(?PipelineInterface $value)
    {
        /** @var static<T,TProvider> */
        return $this->withValueB(__FUNCTION__, $value);
    }
}
