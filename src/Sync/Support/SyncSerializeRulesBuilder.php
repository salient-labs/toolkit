<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concept\Builder;
use Lkrms\Support\Date\DateFormatter;
use Lkrms\Sync\Contract\ISyncEntity;
use Closure;

/**
 * A fluent SyncSerializeRules factory
 *
 * @template-covariant TEntity of ISyncEntity
 *
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
 *
 * @extends Builder<SyncSerializeRules<TEntity>>
 *
 * @generated
 */
final class SyncSerializeRulesBuilder extends Builder
{
    /**
     * @inheritDoc
     */
    protected static function getService(): string
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
        return $this->withValueB(__FUNCTION__, $value);
    }

    /**
     * Inherit rules from another instance
     *
     * @template T of ISyncEntity
     * @param SyncSerializeRules<T>|null $value
     * @return $this<T>
     */
    public function inherit(?SyncSerializeRules $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }
}
