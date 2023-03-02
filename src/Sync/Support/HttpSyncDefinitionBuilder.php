<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Concept\SyncDefinition;

/**
 * A fluent interface for creating HttpSyncDefinition objects
 *
 * @method static $this build(?IContainer $container = null) Create a new HttpSyncDefinitionBuilder (syntactic sugar for 'new HttpSyncDefinitionBuilder()')
 * @method $this entity(string $value) The ISyncEntity being serviced (see {@see SyncDefinition::$Entity})
 * @method $this provider(HttpSyncProvider $value) The ISyncProvider servicing the entity
 * @method $this operations(int[] $value) A list of supported sync operations (see {@see SyncDefinition::$Operations})
 * @method $this path(Closure|string|null $value) Closure signature: `fn(int $operation, ISyncContext $ctx, ...$args): string`
 * @method $this query(Closure|array|null $value) Closure signature: `fn(int $operation, ISyncContext $ctx, ...$args): ?array`
 * @method $this headersCallback(?Closure $value) Closure signature: `fn(Curler $curler, int $operation, ISyncContext $ctx, ...$args): ?CurlerHeaders`
 * @method $this pagerCallback(?Closure $value) Closure signature: `fn(Curler $curler, int $operation, ISyncContext $ctx, ...$args): ?ICurlerPager`
 * @method $this request(Closure|HttpSyncDefinitionRequest|null $value) If set, `$path`, `$query`, `$headersCallback` and `$pagerCallback` are ignored. Closure signature: `fn(int $operation, ISyncContext $ctx, ...$args): HttpSyncDefinitionRequest`
 * @method $this conformity(int $value) The conformity level of data returned by the provider for this entity (see {@see SyncDefinition::$Conformity})
 * @method $this filterPolicy(int $value) The action to take when filters are ignored by the provider (see {@see SyncDefinition::$FilterPolicy})
 * @method $this expiry(?int $value) Passed to the provider's getCurler() method
 * @method $this methodMap(array $value) An array that maps sync operations to HTTP request methods (see {@see HttpSyncDefinition::$MethodMap})
 * @method $this overrides(array $value) See {@see SyncDefinition::$Overrides}
 * @method $this dataToEntityPipeline(?IPipeline $value) A pipeline that maps data from the provider to entity-compatible associative arrays, or `null` if mapping is not required (see {@see SyncDefinition::$DataToEntityPipeline})
 * @method $this entityToDataPipeline(?IPipeline $value) A pipeline that maps serialized entities to data compatible with the provider, or `null` if mapping is not required (see {@see SyncDefinition::$EntityToDataPipeline})
 * @method HttpSyncDefinition go() Return a new HttpSyncDefinition object
 * @method static HttpSyncDefinition|null resolve(HttpSyncDefinition|HttpSyncDefinitionBuilder|null $object) Resolve a HttpSyncDefinitionBuilder or HttpSyncDefinition object to a HttpSyncDefinition object
 *
 * @uses HttpSyncDefinition
 * @lkrms-generate-command lk-util generate builder --static-builder=build --terminator=go --static-resolver=resolve 'Lkrms\Sync\Support\HttpSyncDefinition'
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
