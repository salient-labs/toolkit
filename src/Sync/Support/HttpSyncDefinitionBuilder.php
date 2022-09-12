<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;

/**
 * A fluent interface for creating HttpSyncDefinition objects
 *
 * @method HttpSyncDefinition go() Return a new HttpSyncDefinition object
 * @method $this entity(string $value)
 * @method $this provider(\Lkrms\Sync\Provider\HttpSyncProvider $value)
 * @method $this path(string $value)
 * @method $this operations(int[] $value)
 * @method $this overrides(array<int,\Closure> $value)
 * @method $this dataToEntityPipeline(?\Lkrms\Contract\IPipeline $value)
 * @method $this entityToDataPipeline(?\Lkrms\Contract\IPipeline $value)
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
