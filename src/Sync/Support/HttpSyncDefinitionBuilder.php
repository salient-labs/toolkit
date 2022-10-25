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
 * @method static $this entity(string $value) See {@see SyncDefinition::$Entity}
 * @method static $this provider(HttpSyncProvider $value) See {@see HttpSyncDefinition::$Provider}
 * @method static $this operations(int[] $value) See {@see HttpSyncDefinition::$Operations}
 * @method static $this path(Closure|string|null $value) Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): string` (see {@see HttpSyncDefinition::$Path})
 * @method static $this query(Closure|array|null $value) Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): ?array` (see {@see HttpSyncDefinition::$Query})
 * @method static $this request(Closure|HttpSyncDefinitionRequest|null $value) If set, `$path` and `$query` are ignored. Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): HttpSyncDefinitionRequest` (see {@see HttpSyncDefinition::$Request})
 * @method static $this conformity(int $value) See {@see SyncDefinition::$Conformity}
 * @method static $this expiry(?int $value) See {@see HttpSyncDefinition::$Expiry}
 * @method static $this methodMap(array $value) See {@see HttpSyncDefinition::$MethodMap}
 * @method static $this overrides(array $value) See {@see HttpSyncDefinition::$Overrides}
 * @method static $this dataToEntityPipeline(?IPipelineImmutable $value) A pipeline that converts data received from the provider to an associative array from which the entity can be instantiated, or `null` if the entity is not supported or conversion is not required (see {@see SyncDefinition::$DataToEntityPipeline})
 * @method static $this entityToDataPipeline(?IPipelineImmutable $value) A pipeline that converts a serialized instance of the entity to data compatible with the provider, or `null` if the entity is not supported or conversion is not required (see {@see SyncDefinition::$EntityToDataPipeline})
 * @method static HttpSyncDefinition go() Return a new HttpSyncDefinition object
 * @method static HttpSyncDefinition resolve(HttpSyncDefinition|HttpSyncDefinitionBuilder $object) Resolve a HttpSyncDefinitionBuilder or HttpSyncDefinition object to a HttpSyncDefinition object
 *
 * @uses HttpSyncDefinition
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Sync\Support\HttpSyncDefinition' --static-builder='build' --terminator='go' --static-resolver='resolve'
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
