<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Closure;
use DateTimeInterface;

/**
 * @api
 *
 * @template TEntity of object
 */
interface SerializeRulesInterface extends Immutable
{
    /**
     * Get the entity to which the rules apply
     *
     * @return class-string<TEntity>
     */
    public function getEntity(): string;

    /**
     * Merge with another instance, giving precedence to its values if there are
     * any conflicts
     *
     * An exception is thrown if `$rules` does not apply to the same entity or
     * one of its subclasses.
     *
     * @template T of TEntity
     *
     * @param static<T> $rules
     * @return static<T>
     */
    public function merge(SerializeRulesInterface $rules): SerializeRulesInterface;

    /**
     * Get keys to remove from a serialized class at a given path
     *
     * If `$baseClass` is given, rules applied to `$class` and its parents up to
     * but not including `$baseClass` are considered, otherwise the only
     * class-based rules considered are those applied to `$class`.
     *
     * @template T0 of object
     * @template T1 of T0
     *
     * @param class-string<T1>|null $class
     * @param class-string<T0>|null $baseClass
     * @param string[] $path
     * @return array<string,string> Keys and values are the same.
     */
    public function getRemovableKeys(?string $class, ?string $baseClass, array $path): array;

    /**
     * Get keys to replace in a serialized class at a given path
     *
     * If `$baseClass` is given, rules applied to `$class` and its parents up to
     * but not including `$baseClass` are considered, otherwise the only
     * class-based rules considered are those applied to `$class`.
     *
     * @template T0 of object
     * @template T1 of T0
     *
     * @param class-string<T1>|null $class
     * @param class-string<T0>|null $baseClass
     * @param string[] $path
     * @return array<string,array{int|string|null,(Closure(mixed $value): mixed)|null}> Each
     * key is mapped to an array with two values, one of which may be `null`:
     * - a new key for the value
     * - a closure to return a new value for the key
     */
    public function getReplaceableKeys(?string $class, ?string $baseClass, array $path): array;

    /**
     * Get a date formatter to serialize date and time values
     */
    public function getDateFormatter(): ?DateFormatterInterface;

    /**
     * Get an instance that uses the given date formatter to serialize date and
     * time values
     *
     * {@see DateTimeInterface} instances are serialized as ISO-8601 strings if
     * no date formatter is provided.
     *
     * @return static
     */
    public function withDateFormatter(?DateFormatterInterface $formatter);

    /**
     * Check if undeclared property values are serialized
     */
    public function getIncludeMeta(): bool;

    /**
     * Get an instance that serializes undeclared property values
     *
     * @return static
     */
    public function withIncludeMeta(?bool $include = true);

    /**
     * Check if serialized entities are sorted by key
     */
    public function getSortByKey(): bool;

    /**
     * Get an instance that sorts serialized entities by key
     *
     * @return static
     */
    public function withSortByKey(?bool $sort = true);

    /**
     * Get the maximum depth of nested values
     */
    public function getMaxDepth(): int;

    /**
     * Get an instance that limits the depth of nested values
     *
     * @return static
     */
    public function withMaxDepth(?int $depth);

    /**
     * Check if recursion is detected when serializing nested entities
     */
    public function getDetectRecursion(): bool;

    /**
     * Get an instance that detects recursion when serializing nested entities
     *
     * If it would be impossible for a circular reference to arise during
     * serialization, disable recursion detection to improve performance and
     * reduce memory consumption.
     *
     * @return static
     */
    public function withDetectRecursion(?bool $detect = true);

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
    public function withRecurseRules(?bool $recurse = true);
}
