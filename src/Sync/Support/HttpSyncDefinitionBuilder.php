<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;

/**
 * A fluent interface for creating HttpSyncDefinition objects
 *
 * @method static $this build(?IContainer $container = null) Create a new HttpSyncDefinitionBuilder (syntactic sugar for 'new HttpSyncDefinitionBuilder()')
 * @method $this entity(string $value) See {@see SyncDefinition::$Entity}
 * @method $this provider(HttpSyncProvider $value) See {@see HttpSyncDefinition::$Provider}
 * @method $this operations(int[] $value) See {@see HttpSyncDefinition::$Operations}
 * @method $this path(Closure|string|null $value) Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): string` (see {@see HttpSyncDefinition::$Path})
 * @method $this query(Closure|array|null $value) Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): ?array` (see {@see HttpSyncDefinition::$Query})
 * @method $this conformity(int $value) See {@see SyncDefinition::$Conformity}
 * @method $this expiry(?int $value) See {@see HttpSyncDefinition::$Expiry}
 * @method $this methodMap(array $value) See {@see HttpSyncDefinition::$MethodMap}
 * @method $this overrides(array $value) See {@see HttpSyncDefinition::$Overrides}
 * @method $this dataToEntityPipeline(?IPipelineImmutable $value) A pipeline that converts data received from the provider to an associative array from which the entity can be instantiated, or `null` if the entity is not supported or conversion is not required (see {@see SyncDefinition::$DataToEntityPipeline})
 * @method $this entityToDataPipeline(?IPipelineImmutable $value) A pipeline that converts a serialized instance of the entity to data compatible with the provider, or `null` if the entity is not supported or conversion is not required (see {@see SyncDefinition::$EntityToDataPipeline})
 * @method HttpSyncDefinition go() Return a new HttpSyncDefinition object
 *
 * @uses HttpSyncDefinition
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Sync\Support\HttpSyncDefinition' --static-builder='build' --terminator='go'
 */
final class HttpSyncDefinitionBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return HttpSyncDefinition::class;
    }
}
