<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Contract\ISyncEntity;

/**
 * A fluent interface for creating SyncSerializeRules objects
 *
 * @template-covariant TEntity of ISyncEntity
 *
 * @method static $this build(?IContainer $container = null) Create a new SyncSerializeRulesBuilder (syntactic sugar for 'new SyncSerializeRulesBuilder()')
 * @method $this dateFormatter(?DateFormatter $value) Override the default date formatter (default: null)
 * @method $this includeMeta(?bool $value = true) Include undeclared property values? (default: true)
 * @method $this sortByKey(?bool $value = true) Sort arrays by key? (default: false)
 * @method $this maxDepth(?int $value) Throw an exception when values are nested beyond this depth (default: 99) (see {@see SyncSerializeRules::$MaxDepth})
 * @method $this detectRecursion(?bool $value = true) Check for recursion? (default: true) (see {@see SyncSerializeRules::$DetectRecursion})
 * @method $this removeCanonicalId(?bool $value = true) Remove CanonicalId from sync entities? (default: true) (see {@see SyncSerializeRules::$RemoveCanonicalId})
 * @method $this remove(array<array<array<string|Closure>|string>|array<string|Closure>|string> $value) Values to remove (see {@see SyncSerializeRules::$Remove})
 * @method $this replace(array<array<array<string|Closure>|string>|array<string|Closure>|string> $value) Values to replace with IDs (see {@see SyncSerializeRules::$Replace})
 * @method $this recurseRules(?bool $value = true) Apply path-based rules to every instance of $Entity? (default: true)
 * @method $this flags(?int $value) Set SyncSerializeRules::$Flags
 * @method $this inherit(SyncSerializeRules<TEntity>|null $value) Pass $value to `$inherit` in SyncSerializeRules::__construct()
 * @method mixed get(string $name) The value of $name if applied to the unresolved SyncSerializeRules by calling $name(), otherwise null
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved SyncSerializeRules by calling $name()
 * @method SyncSerializeRules go() Get a new SyncSerializeRules object
 * @method static SyncSerializeRules resolve(SyncSerializeRules|SyncSerializeRulesBuilder $object) Resolve a SyncSerializeRulesBuilder or SyncSerializeRules object to a SyncSerializeRules object
 *
 * @uses SyncSerializeRules
 *
 * @extends Builder<SyncSerializeRules<TEntity>>
 */
final class SyncSerializeRulesBuilder extends Builder
{
    /**
     * @internal
     * @return class-string<SyncSerializeRules<TEntity>>
     */
    protected static function getClassName(): string
    {
        return SyncSerializeRules::class;
    }

    /**
     * The class name of the SyncEntity being serialized (required)
     *
     * @template T of ISyncEntity
     * @param class-string<T> $value
     * @return $this<T>
     */
    public function entity(string $value)
    {
        return $this->getWithValue(__FUNCTION__, $value);
    }
}
