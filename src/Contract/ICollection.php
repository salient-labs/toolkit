<?php declare(strict_types=1);

namespace Lkrms\Contract;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * Implements Iterator, ArrayAccess and Countable to provide array-like objects
 *
 * @template T
 * @extends Iterator<int,T>
 * @extends ArrayAccess<int,T>
 */
interface ICollection extends Iterator, ArrayAccess, Countable
{
    /**
     * Apply a callback to every item
     *
     * @param callable $callback
     * ```php
     * fn(T $item, ?T $nextItem, ?T $prevItem): mixed
     * ```
     * @phpstan-param callable(T, ?T, ?T): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback);

    /**
     * Get a new instance with items that satisfy a callback
     *
     * Analogous to `array_filter()`.
     *
     * @param callable $callback
     * ```php
     * fn(T $item, ?T $nextItem, ?T $prevItem): bool
     * ```
     * @phpstan-param callable(T, ?T, ?T): bool $callback
     * @return static
     */
    public function filter(callable $callback);

    /**
     * Get the first item that satisfies a callback, or false if no such item is
     * in the collection
     *
     * @param callable $callback
     * ```php
     * fn(T $item, ?T $nextItem, ?T $prevItem): bool
     * ```
     * @phpstan-param callable(T, ?T, ?T): bool $callback
     * @return T|false
     */
    public function find(callable $callback);

    /**
     * Get a new instance with items extracted from the collection
     *
     * Analogous to `array_slice()`.
     *
     * @return static
     */
    public function slice(int $offset, ?int $length = null, bool $preserveKeys = true);

    /**
     * True if an item is in the collection
     *
     * @param T $item
     */
    public function has($item, bool $strict = false): bool;

    /**
     * Get the first key at which an item is found, or false if it's not in the
     * collection
     *
     * @param T $item
     * @return int|string|false
     */
    public function keyOf($item, bool $strict = false);

    /**
     * Get the first item equal but not necessarily identical to $item, or false
     * if no such item is in the collection
     *
     * @param T $item
     * @return T|false
     */
    public function get($item);

    /**
     * Get an array with each item
     *
     * @return T[]
     */
    public function toArray(bool $preserveKeys = true): array;

    /**
     * Get the first item, or false if the collection is empty
     *
     * @return T|false
     */
    public function first();

    /**
     * Get the last item, or false if the collection is empty
     *
     * @return T|false
     */
    public function last();

    /**
     * Get the nth item (1-based), or false if no such item is in the collection
     *
     * If `$n` is negative, the nth item from the end of the collection is
     * returned.
     *
     * @return T|false
     */
    public function nth(int $n);

    /**
     * Shift an item off the beginning of the collection
     *
     * @return T|false
     */
    public function shift();
}
