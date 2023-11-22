<?php declare(strict_types=1);

namespace Lkrms\Contract;

use ArrayAccess;
use Countable;
use IteratorAggregate;

/**
 * An array-like collection of items
 *
 * @template TKey
 * @template TValue
 *
 * @extends IteratorAggregate<TKey,TValue>
 * @extends ArrayAccess<TKey,TValue>
 */
interface ICollection extends IteratorAggregate, ArrayAccess, Countable
{
    /**
     * Add or replace an item with a given key
     *
     * @param TKey $key
     * @param TValue $value
     * @return static
     */
    public function set($key, $value);

    /**
     * Remove an item with a given key
     *
     * @param TKey $key
     * @param TValue|null $value Receives the value removed from the collection,
     * or `null` if it does not exist.
     * @param-out TValue|null $value
     * @return static
     */
    public function unset($key, &$value = null);

    /**
     * Add or replace items from an array or Traversable
     *
     * @param static|iterable<TKey,TValue> $items
     * @return static
     */
    public function merge($items);

    /**
     * Sort items in the collection
     *
     * @return static
     */
    public function sort();

    /**
     * Reverse the order of items in the collection
     *
     * @return static
     */
    public function reverse();

    /**
     * Apply a callback to items in the collection
     *
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback);

    /**
     * Reduce the collection to items that satisfy a callback
     *
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): bool $callback
     * @return static
     */
    public function filter(callable $callback);

    /**
     * Get the first item that satisfies a callback, or null if there is no such
     * item in the collection
     *
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): bool $callback
     * @return TValue|null
     */
    public function find(callable $callback);

    /**
     * Extract a slice of the collection
     *
     * @return static
     */
    public function slice(int $offset, ?int $length = null);

    /**
     * True if a value is in the collection
     *
     * @param TValue $value
     */
    public function has($value, bool $strict = false): bool;

    /**
     * Get the first key at which a value is found, or null if it's not in the
     * collection
     *
     * @param TValue $value
     * @return TKey|null
     */
    public function keyOf($value, bool $strict = false);

    /**
     * Get the first item equal but not necessarily identical to a value, or
     * null if it's not in the collection
     *
     * @param TValue $value
     * @return TValue|null
     */
    public function get($value);

    /**
     * Get all items in the collection
     *
     * @return array<TKey,TValue>
     */
    public function all(): array;

    /**
     * Get the first item, or null if the collection is empty
     *
     * @return TValue|null
     */
    public function first();

    /**
     * Get the last item, or null if the collection is empty
     *
     * @return TValue|null
     */
    public function last();

    /**
     * Get the nth item (1-based), or null if there is no such item in the
     * collection
     *
     * If `$n` is negative, the nth item from the end of the collection is
     * returned.
     *
     * @return TValue|null
     */
    public function nth(int $n);

    /**
     * Pop an item off the end of the collection
     *
     * @param TValue|null $last Receives the value removed from the collection,
     * or `null` if the collection is empty.
     * @param-out TValue|null $last
     * @return static
     */
    public function pop(&$last = null);

    /**
     * Shift an item off the beginning of the collection
     *
     * @param TValue|null $first Receives the value removed from the collection,
     * or `null` if the collection is empty.
     * @param-out TValue|null $first
     * @return static
     */
    public function shift(&$first = null);
}
