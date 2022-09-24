<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IPipelineImmutable;
use Lkrms\Sync\Contract\ISyncProvider;

/**
 * A fluent interface for creating SyncDefinition objects
 *
 * @method static $this build(?IContainer $container = null) Create a new SyncDefinitionBuilder (syntactic sugar for 'new SyncDefinitionBuilder()')
 * @method $this entity(string $value)
 * @method $this provider(ISyncProvider $value)
 * @method $this conformity(int $value)
 * @method $this container(?IContainer $value)
 * @method $this dataToEntityPipeline(?IPipelineImmutable $value)
 * @method $this entityToDataPipeline(?IPipelineImmutable $value)
 * @method SyncDefinition go() Return a new SyncDefinition object
 *
 * @uses SyncDefinition
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Sync\Support\SyncDefinition' --static-builder='build' --terminator='go' --no-final
 */
abstract class SyncDefinitionBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return SyncDefinition::class;
    }
}
