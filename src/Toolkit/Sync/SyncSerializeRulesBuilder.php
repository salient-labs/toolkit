<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Core\AbstractBuilder;
use Closure;

/**
 * A fluent SyncSerializeRules factory
 *
 * @method $this dateFormatter(?DateFormatterInterface $value) Override the default date formatter (default: null)
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
 * @template-covariant TEntity of SyncEntityInterface
 *
 * @extends AbstractBuilder<SyncSerializeRules<TEntity>>
 *
 * @generated
 */
final class SyncSerializeRulesBuilder extends AbstractBuilder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return SyncSerializeRules::class;
    }

    /**
     * The class name of the AbstractSyncEntity being serialized (required)
     *
     * @template T of SyncEntityInterface
     *
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
     * @template T of SyncEntityInterface
     *
     * @param SyncSerializeRules<T>|null $value
     * @return $this<T>
     */
    public function inherit(?SyncSerializeRules $value)
    {
        return $this->withValueB(__FUNCTION__, $value);
    }
}
