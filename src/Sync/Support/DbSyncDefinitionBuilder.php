<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IPipeline;
use Lkrms\Support\Catalog\ArrayKeyConformity;
use Lkrms\Support\Catalog\ArrayMapperFlag;
use Lkrms\Sync\Catalog\FilterPolicy;
use Lkrms\Sync\Catalog\SyncEntitySource;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\DbSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Closure;

/**
 * Creates DbSyncDefinition objects via a fluent interface
 *
 * @template TEntity of ISyncEntity
 * @template TProvider of DbSyncProvider
 *
 * @method $this operations(array<OP::*> $value) A list of supported sync operations
 * @method $this table(?string $value) Set DbSyncDefinition::$Table
 * @method $this conformity(ArrayKeyConformity::* $value) The conformity level of data returned by the provider for this entity (see {@see SyncDefinition::$Conformity})
 * @method $this filterPolicy(FilterPolicy::*|null $value) The action to take when filters are unclaimed by the provider (see {@see SyncDefinition::$FilterPolicy})
 * @method $this overrides(array<int-mask-of<OP::*>,Closure(DbSyncDefinition<TEntity,TProvider>, OP::*, ISyncContext, mixed...): (iterable<TEntity>|TEntity)> $value) An array that maps sync operations to closures that override other implementations (see {@see SyncDefinition::$Overrides})
 * @method $this keyMap(array<array-key,array-key|array-key[]>|null $value) An array that maps provider (backend) keys to one or more entity keys (see {@see SyncDefinition::$KeyMap})
 * @method $this keyMapFlags(int-mask-of<ArrayMapperFlag::*> $value) Passed to the array mapper if `$keyMap` is provided
 * @method $this readFromReadList(bool $value = true) If true, perform READ operations by iterating over entities returned by READ_LIST (default: false; see {@see SyncDefinition::$ReadFromReadList})
 * @method $this returnEntitiesFrom(SyncEntitySource::*|null $value) Where to acquire entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 *
 * @uses DbSyncDefinition
 *
 * @extends Builder<DbSyncDefinition<TEntity,TProvider>>
 */
final class DbSyncDefinitionBuilder extends Builder
{
    /**
     * @inheritDoc
     */
    protected static function getService(): string
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

    /**
     * A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required
     *
     * @template T of ISyncEntity
     * @param IPipeline<mixed[],T,array{0:OP::*,1:ISyncContext,2?:int|string|T|T[]|null,...}>|null $value
     * @return $this<T,TProvider>
     */
    public function pipelineFromBackend(?IPipeline $value)
    {
        return $this->getWithValue(__FUNCTION__, $value);
    }

    /**
     * A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required
     *
     * @template T of ISyncEntity
     * @param IPipeline<T,mixed[],array{0:OP::*,1:ISyncContext,2?:int|string|T|T[]|null,...}>|null $value
     * @return $this<T,TProvider>
     */
    public function pipelineToBackend(?IPipeline $value)
    {
        return $this->getWithValue(__FUNCTION__, $value);
    }
}
