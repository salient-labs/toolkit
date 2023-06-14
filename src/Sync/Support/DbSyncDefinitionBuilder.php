<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncFilterPolicy;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Concept\DbSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;

/**
 * A fluent interface for creating DbSyncDefinition objects
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of DbSyncProvider
 *
 * @method static $this build(?IContainer $container = null) Create a new DbSyncDefinitionBuilder (syntactic sugar for 'new DbSyncDefinitionBuilder()')
 * @method $this operations(array<SyncOperation::*> $value) A list of supported sync operations
 * @method $this table(?string $value) Set DbSyncDefinition::$Table
 * @method $this conformity(ArrayKeyConformity::* $value) The conformity level of data returned by the provider for this entity (see {@see SyncDefinition::$Conformity})
 * @method $this filterPolicy(SyncFilterPolicy::* $value) The action to take when filters are ignored by the provider (see {@see SyncDefinition::$FilterPolicy})
 * @method $this overrides(array<SyncOperation::*,Closure(ISyncDefinition<TEntity,TProvider>, SyncOperation::*, ISyncContext, mixed...): mixed> $value) An array that maps sync operations to closures that override any other implementations (see {@see SyncDefinition::$Overrides})
 * @method $this pipelineFromBackend(IPipeline<mixed[],TEntity,array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $value) A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required
 * @method $this pipelineToBackend(IPipeline<TEntity,mixed[],array{0:int,1:ISyncContext,2?:int|string|TEntity|TEntity[]|null,...}>|null $value) A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required
 * @method $this returnEntitiesFrom(SyncEntitySource::*|null $value) Where to acquire entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 * @method mixed get(string $name) The value of $name if applied to the unresolved DbSyncDefinition by calling $name(), otherwise null
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved DbSyncDefinition by calling $name()
 * @method DbSyncDefinition go() Get a new DbSyncDefinition object
 * @method static DbSyncDefinition resolve(DbSyncDefinition|DbSyncDefinitionBuilder $object) Resolve a DbSyncDefinitionBuilder or DbSyncDefinition object to a DbSyncDefinition object
 *
 * @uses DbSyncDefinition
 *
 * @extends Builder<DbSyncDefinition<TEntity,TProvider>>
 */
final class DbSyncDefinitionBuilder extends Builder
{
    /**
     * @internal
     * @return class-string<DbSyncDefinition<TEntity,TProvider>>
     */
    protected static function getClassName(): string
    {
        return DbSyncDefinition::class;
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
     * @template T of DbSyncProvider
     * @param T $value
     * @return $this<TEntity,T>
     */
    public function provider(DbSyncProvider $value)
    {
        return $this->getWithValue(__FUNCTION__, $value);
    }
}
