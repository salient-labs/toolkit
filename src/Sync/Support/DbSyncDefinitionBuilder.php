<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Sync\Concept\DbSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;

/**
 * A fluent interface for creating DbSyncDefinition objects
 *
 * @method static $this build(?IContainer $container = null) Create a new DbSyncDefinitionBuilder (syntactic sugar for 'new DbSyncDefinitionBuilder()')
 * @method $this entity(string $value) Set SyncDefinition::$Entity
 * @method $this provider(DbSyncProvider $value) Set DbSyncDefinition::$Provider
 * @method $this operations(int[] $value) Set DbSyncDefinition::$Operations
 * @method $this table(?string $value) Set DbSyncDefinition::$Table
 * @method $this conformity(int $value) See {@see SyncDefinition::$Conformity}
 * @method $this filterPolicy(int $value) One of the {@see SyncFilterPolicy} values.  To prevent, say, a filtered {@see SyncOperation::READ_LIST} request returning every entity of that type when a provider doesn't use the filter, the default policy is {@see SyncFilterPolicy::THROW_EXCEPTION}.  See {@see \Lkrms\Sync\Contract\ISyncContext::withArgs()} for more information (see {@see SyncDefinition::$FilterPolicy})
 * @method $this overrides(array $value) See {@see DbSyncDefinition::$Overrides}
 * @method $this dataToEntityPipeline(?IPipelineImmutable $value) A pipeline that converts data received from the provider to an associative array from which the entity can be instantiated, or `null` if the entity is not supported or conversion is not required
 * @method $this entityToDataPipeline(?IPipelineImmutable $value) A pipeline that converts a serialized instance of the entity to data compatible with the provider, or `null` if the entity is not supported or conversion is not required
 * @method DbSyncDefinition go() Return a new DbSyncDefinition object
 * @method static DbSyncDefinition|null resolve(DbSyncDefinition|DbSyncDefinitionBuilder|null $object) Resolve a DbSyncDefinitionBuilder or DbSyncDefinition object to a DbSyncDefinition object
 *
 * @uses DbSyncDefinition
 * @lkrms-generate-command lk-util generate builder --static-builder=build --terminator=go --static-resolver=resolve 'Lkrms\Sync\Support\DbSyncDefinition'
 */
final class DbSyncDefinitionBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return DbSyncDefinition::class;
    }
}
