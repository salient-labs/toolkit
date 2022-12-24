<?php declare(strict_types=1);

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
 * @method $this headersCallback(?Closure $value) Closure signature: `fn(Curler $curler, int $operation, SyncContext $ctx, ...$args): ?CurlerHeaders` (see {@see HttpSyncDefinition::$HeadersCallback})
 * @method $this pagerCallback(?Closure $value) Closure signature: `fn(Curler $curler, int $operation, SyncContext $ctx, ...$args): ?ICurlerPager` (see {@see HttpSyncDefinition::$PagerCallback})
 * @method $this request(Closure|HttpSyncDefinitionRequest|null $value) If set, `$path`, `$query`, `$headersCallback` and `$pagerCallback` are ignored. Closure signature: `fn(int $operation, SyncContext $ctx, ...$args): HttpSyncDefinitionRequest` (see {@see HttpSyncDefinition::$Request})
 * @method $this conformity(int $value) See {@see SyncDefinition::$Conformity}
 * @method $this filterPolicy(int $value) One of the {@see SyncFilterPolicy} values.  To prevent, say, a filtered {@see SyncOperation::READ_LIST} request returning every entity of that type when a provider doesn't use the filter, the default policy is {@see SyncFilterPolicy::THROW_EXCEPTION}.  See {@see \Lkrms\Sync\Contract\ISyncContext::withArgs()} for more information (see {@see SyncDefinition::$FilterPolicy})
 * @method $this expiry(?int $value) See {@see HttpSyncDefinition::$Expiry}
 * @method $this methodMap(array $value) See {@see HttpSyncDefinition::$MethodMap}
 * @method $this overrides(array $value) See {@see HttpSyncDefinition::$Overrides}
 * @method $this dataToEntityPipeline(?IPipelineImmutable $value) A pipeline that converts data received from the provider to an associative array from which the entity can be instantiated, or `null` if the entity is not supported or conversion is not required (see {@see SyncDefinition::$DataToEntityPipeline})
 * @method $this entityToDataPipeline(?IPipelineImmutable $value) A pipeline that converts a serialized instance of the entity to data compatible with the provider, or `null` if the entity is not supported or conversion is not required (see {@see SyncDefinition::$EntityToDataPipeline})
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
