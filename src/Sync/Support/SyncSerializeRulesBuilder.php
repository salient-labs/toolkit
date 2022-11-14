<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Support\DateFormatter;

/**
 * A fluent interface for creating SyncSerializeRules objects
 *
 * @method static $this build(?IContainer $container = null) Create a new SyncSerializeRulesBuilder (syntactic sugar for 'new SyncSerializeRulesBuilder()')
 * @method static $this entity(string $value) The class name of the SyncEntity being serialized (see {@see SyncSerializeRules::$Entity})
 * @method static $this dateFormatter(?DateFormatter $value) Override the default date formatter (see {@see SyncSerializeRules::$DateFormatter})
 * @method static $this includeMeta(bool $value = true) Include undeclared property values? (see {@see SyncSerializeRules::$IncludeMeta})
 * @method static $this sortByKey(bool $value = true) Sort arrays by key? (see {@see SyncSerializeRules::$SortByKey})
 * @method static $this maxDepth(?int $value) Throw an exception when values are nested beyond this depth (see {@see SyncSerializeRules::$MaxDepth})
 * @method static $this detectRecursion(bool $value = true) Check for recursion? (see {@see SyncSerializeRules::$DetectRecursion})
 * @method static $this removeCanonicalId(bool $value = true) Remove CanonicalId from sync entities? (see {@see SyncSerializeRules::$RemoveCanonicalId})
 * @method static $this remove(array $value) Values to remove (see {@see SyncSerializeRules::$Remove})
 * @method static $this replace(array $value) Values to replace with IDs (see {@see SyncSerializeRules::$Replace})
 * @method static $this recurseRules(bool $value = true) Apply path-based rules to every instance of $Entity? (see {@see SyncSerializeRules::$RecurseRules})
 * @method static $this flags(int $value) See {@see SyncSerializeRules::$Flags}
 * @method static $this inherit(SyncSerializeRules|SyncSerializeRulesBuilder|null $value)
 * @method static SyncSerializeRules go() Return a new SyncSerializeRules object
 * @method static SyncSerializeRules|null resolve(SyncSerializeRules|SyncSerializeRulesBuilder|null $object) Resolve a SyncSerializeRulesBuilder or SyncSerializeRules object to a SyncSerializeRules object
 *
 * @uses SyncSerializeRules
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Sync\Support\SyncSerializeRules' --static-builder='build' --terminator='go' --static-resolver='resolve'
 */
final class SyncSerializeRulesBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return SyncSerializeRules::class;
    }
}
