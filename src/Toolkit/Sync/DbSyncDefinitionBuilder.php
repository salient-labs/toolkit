<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Pipeline\PipelineInterface;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Core\Catalog\ArrayMapperFlag;
use Salient\Core\Catalog\ListConformity;
use Salient\Core\AbstractBuilder;
use Salient\Sync\Catalog\FilterPolicy;
use Salient\Sync\Catalog\SyncEntitySource;
use Salient\Sync\Catalog\SyncOperation as OP;
use Closure;

/**
 * A fluent DbSyncDefinition factory
 *
 * @method $this operations(array<OP::*> $value) A list of supported sync operations
 * @method $this table(?string $value) Set DbSyncDefinition::$Table
 * @method $this conformity(ListConformity::* $value) The conformity level of data returned by the provider for this entity (see {@see AbstractSyncDefinition::$Conformity})
 * @method $this filterPolicy(FilterPolicy::*|null $value) The action to take when filters are unclaimed by the provider (see {@see AbstractSyncDefinition::$FilterPolicy})
 * @method $this overrides(array<int-mask-of<OP::*>,Closure(DbSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): (iterable<TEntity>|TEntity)> $value) An array that maps sync operations to closures that override other implementations (see {@see AbstractSyncDefinition::$Overrides})
 * @method $this keyMap(array<array-key,array-key|array-key[]>|null $value) An array that maps provider (backend) keys to one or more entity keys (see {@see AbstractSyncDefinition::$KeyMap})
 * @method $this keyMapFlags(int-mask-of<ArrayMapperFlag::*> $value) Passed to the array mapper if `$keyMap` is provided
 * @method $this readFromReadList(bool $value = true) If true, perform READ operations by iterating over entities returned by READ_LIST (default: false; see {@see AbstractSyncDefinition::$ReadFromReadList})
 * @method $this returnEntitiesFrom(SyncEntitySource::*|null $value) Where to acquire entity data for the return value of a successful CREATE, UPDATE or DELETE operation
 *
 * @template TEntity of SyncEntityInterface
 * @template TProvider of DbSyncProvider
 *
 * @extends AbstractBuilder<DbSyncDefinition<TEntity,TProvider>>
 *
 * @generated
 */
final class DbSyncDefinitionBuilder extends AbstractBuilder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return DbSyncDefinition::class;
    }

    /**
     * The SyncEntityInterface being serviced
     *
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $value
     * @return $this<T,TProvider>
     */
    public function entity(string $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * The SyncProviderInterface servicing the entity
     *
     * @template T of DbSyncProvider
     *
     * @param T $value
     * @return $this<TEntity,T>
     */
    public function provider(DbSyncProvider $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required
     *
     * @template T of SyncEntityInterface
     *
     * @param PipelineInterface<mixed[],T,array{0:OP::*,1:SyncContextInterface,2?:int|string|T|T[]|null,...}>|null $value
     * @return $this<T,TProvider>
     */
    public function pipelineFromBackend(?PipelineInterface $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required
     *
     * @template T of SyncEntityInterface
     *
     * @param PipelineInterface<T,mixed[],array{0:OP::*,1:SyncContextInterface,2?:int|string|T|T[]|null,...}>|null $value
     * @return $this<T,TProvider>
     */
    public function pipelineToBackend(?PipelineInterface $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }
}
