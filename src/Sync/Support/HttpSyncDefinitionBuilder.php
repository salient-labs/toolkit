<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IPipeline;
use Lkrms\Sync\Provider\HttpSyncProvider;

/**
 * A fluent interface for creating HttpSyncDefinition objects
 *
 * @method HttpSyncDefinition go() Return a new HttpSyncDefinition object
 * @method static $this build() Create a new HttpSyncDefinitionBuilder
 * @method $this entity(string $value)
 * @method $this provider(HttpSyncProvider $value)
 * @method $this path(string $value)
 * @method $this operations(int[] $value)
 * @method $this overrides(array $value)
 * @method $this dataToEntityPipeline(?IPipeline $value)
 * @method $this entityToDataPipeline(?IPipeline $value)
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
