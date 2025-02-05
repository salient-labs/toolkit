<?php declare(strict_types=1);

namespace Salient\Contract\Core\Entity;

use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\Immutable;
use Closure;

/**
 * @api
 *
 * @template TEntity of object
 */
interface SerializeRulesInterface extends Immutable
{
    /**
     * Get the entity to which the instance applies
     *
     * @return class-string<TEntity>
     */
    public function getEntity(): string;

    /**
     * Check if path-based rules are applied to nested instances of the entity
     */
    public function getRecurseRules(): bool;

    /**
     * Get an instance that applies path-based rules to nested instances of the
     * entity
     *
     * @return static
     */
    public function withRecurseRules(?bool $recurse = true): self;

    /**
     * Merge with another instance, giving precedence to its values if there are
     * any conflicts
     *
     * @param static $rules Must apply to the same entity or one of its
     * subclasses.
     * @return static
     */
    public function merge(self $rules): self;

    /**
     * Get keys to remove from a serialized class at a given path
     *
     * If `$baseClass` is given, rules applied to `$class` and its parents up to
     * but not including `$baseClass` are inherited, otherwise the only
     * class-based rules used are those applied directly to `$class`.
     *
     * @template T0 of object
     * @template T1 of T0
     *
     * @param class-string<T1>|null $class
     * @param class-string<T0>|null $baseClass
     * @param string[] $path
     * @return array<string,string> Keys are mapped to themselves.
     */
    public function getRemovableKeys(?string $class, ?string $baseClass, array $path): array;

    /**
     * Get keys to replace in a serialized class at a given path
     *
     * If `$baseClass` is given, rules applied to `$class` and its parents up to
     * but not including `$baseClass` are inherited, otherwise the only
     * class-based rules used are those applied directly to `$class`.
     *
     * @template T0 of object
     * @template T1 of T0
     *
     * @param class-string<T1>|null $class
     * @param class-string<T0>|null $baseClass
     * @param string[] $path
     * @return array<string,array{string|null,(Closure(mixed $value): mixed)|null}> Each
     * key is mapped to an array with two values, one of which may be `null`:
     * - a new key for the value
     * - a closure to return a new value for the key
     */
    public function getReplaceableKeys(?string $class, ?string $baseClass, array $path): array;

    /**
     * Get the date formatter applied to the instance
     */
    public function getDateFormatter(): ?DateFormatterInterface;

    /**
     * Get an instance with a given date formatter
     *
     * @return static
     */
    public function withDateFormatter(?DateFormatterInterface $formatter): self;

    /**
     * Check if dynamic properties should be included when the entity is
     * serialized
     */
    public function getDynamicProperties(): bool;

    /**
     * Get an instance where dynamic properties are included when the entity is
     * serialized
     *
     * @return static
     */
    public function withDynamicProperties(?bool $include = true): self;

    /**
     * Check if serialized entities should be sorted by key
     */
    public function getSortByKey(): bool;

    /**
     * Get an instance where serialized entities are sorted by key
     *
     * @return static
     */
    public function withSortByKey(?bool $sort = true): self;

    /**
     * Get the maximum depth of nested values
     */
    public function getMaxDepth(): int;

    /**
     * Get an instance where the maximum depth of nested values is a given value
     *
     * @return static
     */
    public function withMaxDepth(?int $depth): self;

    /**
     * Check if recursion detection should be enabled when nested entities are
     * serialized
     */
    public function getDetectRecursion(): bool;

    /**
     * Get an instance where recursion detection is enabled
     *
     * If circular references cannot arise when the entity is serialized,
     * recursion detection should be disabled to improve performance and reduce
     * memory consumption.
     *
     * @return static
     */
    public function withDetectRecursion(?bool $detect = true): self;
}
