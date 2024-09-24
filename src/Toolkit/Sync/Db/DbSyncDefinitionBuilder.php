<?php declare(strict_types=1);

namespace Salient\Sync\Db;

use Salient\Contract\Core\Pipeline\PipelineInterface;
use Salient\Contract\Core\ArrayMapperFlag;
use Salient\Contract\Core\ListConformity;
use Salient\Contract\Sync\EntitySource;
use Salient\Contract\Sync\FilterPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation as OP;
use Salient\Core\AbstractBuilder;
use Salient\Sync\Support\SyncPipelineArgument;
use Salient\Sync\AbstractSyncDefinition;
use Closure;

/**
 * A fluent DbSyncDefinition factory
 *
 * @method $this operations(array<OP::*> $value) Supported sync operations
 * @method $this table(?string $value) Set DbSyncDefinition::$Table
 * @method $this conformity(ListConformity::* $value) Conformity level of data returned by the provider for this entity (see {@see AbstractSyncDefinition::$Conformity})
 * @method $this filterPolicy(FilterPolicy::*|null $value) Action to take when filters are not claimed by the provider (see {@see AbstractSyncDefinition::$FilterPolicy})
 * @method $this overrides(array<int-mask-of<OP::*>,Closure(DbSyncDefinition<TEntity,TProvider>, OP::*, SyncContextInterface, mixed...): (iterable<TEntity>|TEntity)> $value) Array that maps sync operations to closures that override other implementations (see {@see AbstractSyncDefinition::$Overrides})
 * @method $this keyMap(array<array-key,array-key|array-key[]>|null $value) Array that maps keys to properties for entity data returned by the provider (see {@see AbstractSyncDefinition::$KeyMap})
 * @method $this keyMapFlags(int-mask-of<ArrayMapperFlag::*> $value) Array mapper flags used if a key map is provided
 * @method $this readFromList(bool $value = true) Perform READ operations by iterating over entities returned by READ_LIST (default: false; see {@see AbstractSyncDefinition::$ReadFromList})
 * @method $this returnEntitiesFrom(EntitySource::*|null $value) Source of entity data for the return value of a successful CREATE, UPDATE or DELETE operation
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
     * @template T of DbSyncProvider
     *
     * @param T $value
     * @return static<TEntity,T>
     */
    public function provider(DbSyncProvider $value)
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
