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
interface ICollection extends IteratorAggregate, ArrayAccess, Countable, Arrayable
{
    /**
     * Pop an item off the end of the collection
     *
     * @param TValue|false|null $last Receives the value removed from the
     * collection, or `false` if the collection is empty.
     * @param-out TValue|false $last
     * @return static
     */
    public function pop(&$last = null);

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
     * Get the first key at which a value is found, or false if it's not in the
     * collection
     *
     * @param TValue $value
     * @return TKey|false
     */
    public function keyOf($value, bool $strict = false);

    /**
     * Get the first item equal but not necessarily identical to a value, or
     * false if it's not in the collection
     *
     * @param TValue $value
     * @return TValue|false
     */
    public function get($value);

    /**
     * Get all items in the collection
     *
     * @return array<TKey,TValue>
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
     * Get the nth item (1-based), or false if there is no such item in the
     * collection
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
     * @param TValue|false|null $first Receives the value removed from the
     * collection, or `false` if the collection is empty.
     * @param-out TValue|false $first
     * @return static
     */
    public function shift(&$first = null);
}
