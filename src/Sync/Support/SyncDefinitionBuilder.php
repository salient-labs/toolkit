<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipeline;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * A fluent interface for creating SyncDefinition objects
 *
 * @method static $this build(?IContainer $container = null) Create a new SyncDefinitionBuilder (syntactic sugar for 'new SyncDefinitionBuilder()')
 * @method $this entity(string $value)
 * @method $this provider(ISyncProvider $value)
 * @method $this dataToEntityPipeline(?IPipeline $value)
 * @method $this entityToDataPipeline(?IPipeline $value)
 * @method SyncDefinition go() Return a new SyncDefinition object
 *
 * @uses SyncDefinition
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Sync\Support\SyncDefinition' --static-builder='build' --terminator='go' --no-final
 */
class SyncDefinitionBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return SyncDefinition::class;
    }
}
