<?php declare(strict_types=1);

namespace Lkrms\Contract;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * A flexible array-like collection of values
 *
 * @template TKey
 * @template TValue
 *
 * @extends Iterator<TKey,TValue>
 * @extends ArrayAccess<TKey,TValue>
 */
interface ICollection extends Iterator, ArrayAccess, Countable
{
    /**
     * Push one or more items onto the end of the collection
     *
     * @param TValue ...$item
     * @return $this
     */
    public function push(...$item);

    /**
     * Pop an item off the end of the collection
     *
     * @return TValue|false The item removed from the collection, or `false` if
     * the collection is empty.
     */
    public function pop();

    /**
     * Sort items in the collection
     *
     * @return $this
     */
    public function sort();

    /**
     * Reverse the order of items in the collection
     *
     * @return $this
     */
    public function reverse();

    /**
     * Apply a callback to every item in the collection
     *
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback);

    /**
     * Reduce the collection to items that satisfy a callback
     *
     * Analogous to `array_filter()`.
     *
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): bool $callback
     * @return $this
     */
    public function filter(callable $callback);

    /**
     * Get the first item that satisfies a callback, or false if there is no
     * such item in the collection
     *
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): bool $callback
     * @return TValue|false
     */
    public function find(callable $callback);

    /**
     * Extract a slice of the collection
     *
     * Analogous to `array_slice()`.
     *
     * @return $this
     */
    public function slice(int $offset, ?int $length = null);

    /**
     * True if an item is in the collection
     *
     * @param TValue $item
     */
    public function has($item, bool $strict = false): bool;

    /**
     * Get the first key at which an item is found, or false if it's not in the
     * collection
     *
     * @param TValue $item
     * @return TKey|false
     */
    public function keyOf($item, bool $strict = false);

    /**
     * Get the first item equal but not necessarily identical to $item, or false
     * if there is no such item in the collection
     *
     * @param TValue $item
     * @return TValue|false
     */
    public function get($item);

    /**
     * Get all items in the collection
     *
     * @return TValue[]
     */
    public function all(): array;

    /**
     * Get the first item, or false if the collection is empty
     *
     * @return TValue|false
     */
    public function first();

    /**
     * Get the last item, or false if the collection is empty
     *
     * @return TValue|false
     */
    public function last();

    /**
     * Get the nth item (1-based), or false if no such item is in the collection
     *
     * If `$n` is negative, the nth item from the end of the collection is
     * returned.
     *
     * @return TValue|false
     */
    public function nth(int $n);

    /**
     * Shift an item off the beginning of the collection
     *
     * @return TValue|false The item removed from the collection, or `false` if
     * the collection is empty.
     */
    public function shift();

    /**
     * Add one or more items to the beginning of the collection
     *
     * @param TValue ...$item
     * @return $this
     */
    public function unshift(...$item);
}
