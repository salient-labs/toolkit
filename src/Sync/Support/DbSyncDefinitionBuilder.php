<?php

declare(strict_types=1);

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
 * @method $this entity(string $value) See {@see SyncDefinition::$Entity}
 * @method $this provider(DbSyncProvider $value) See {@see DbSyncDefinition::$Provider}
 * @method $this operations(int[] $value) See {@see DbSyncDefinition::$Operations}
 * @method $this table(?string $value) See {@see DbSyncDefinition::$Table}
 * @method $this conformity(int $value) See {@see SyncDefinition::$Conformity}
 * @method $this overrides(array $value) See {@see DbSyncDefinition::$Overrides}
 * @method $this dataToEntityPipeline(?IPipelineImmutable $value) A pipeline that converts data received from the provider to an associative array from which the entity can be instantiated, or `null` if the entity is not supported or conversion is not required (see {@see SyncDefinition::$DataToEntityPipeline})
 * @method $this entityToDataPipeline(?IPipelineImmutable $value) A pipeline that converts a serialized instance of the entity to data compatible with the provider, or `null` if the entity is not supported or conversion is not required (see {@see SyncDefinition::$EntityToDataPipeline})
 * @method DbSyncDefinition go() Return a new DbSyncDefinition object
 *
 * @uses DbSyncDefinition
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Sync\Support\DbSyncDefinition' --static-builder='build' --terminator='go'
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
