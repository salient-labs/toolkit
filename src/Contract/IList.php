<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * An array-like list of items
 *
 * @template TValue
 *
 * @extends ICollection<int,TValue>
 */
interface IList extends ICollection
{
    /**
     * Push one or more items onto the end of the list
     *
     * @param TValue ...$item
     * @return static
     */
    public function push(...$item);

    /**
     * Pop an item off the end of the list
     *
     * @param TValue|false|null $last Receives the value removed from the list,
     * or `false` if the list is empty.
     */
    public function pop(&$last = null);

    /**
     * Sort items in the list
     */
    public function sort();

    /**
     * Reverse the order of items in the list
     */
    public function reverse();

    /**
     * Apply a callback to items in the list
     */
    public function forEach(callable $callback);

    /**
     * Reduce the list to items that satisfy a callback
     */
    public function filter(callable $callback);

    /**
     * Get the first item that satisfies a callback, or false if there is no
     * such item in the list
     */
    public function find(callable $callback);

    /**
     * Extract a slice of the list
     */
    public function slice(int $offset, ?int $length = null);

    /**
     * True if a value is in the list
     */
    public function has($value, bool $strict = false): bool;

    /**
     * Get the first key at which a value is found, or false if it's not in the
     * list
     */
    public function keyOf($value, bool $strict = false);

    /**
     * Get the first item equal but not necessarily identical to a value, or
     * false if it's not in the list
     */
    public function get($value);

    /**
     * Get all items in the list
     */
    public function all(): array;

    /**
     * Get the first item, or false if the list is empty
     */
    public function first();

    /**
     * Get the last item, or false if the list is empty
     */
    public function last();

    /**
     * Get the nth item (1-based), or false if there is no such item in the list
     *
     * If `$n` is negative, the nth item from the end of the list is returned.
     */
    public function nth(int $n);

    /**
     * Shift an item off the beginning of the list
     *
     * @param TValue|false|null $first Receives the value removed from the list,
     * or `false` if the list is empty.
     */
    public function shift(&$first = null);

    /**
     * Add one or more items to the beginning of the list
     *
     * Items are prepended in one operation and stay in the given order.
     *
     * @param TValue ...$item
     * @return static
     */
    public function unshift(...$item);
}
