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
     * @return $this
     * @psalm-param callable(T) $callback
     */
    public function forEach(callable $callback);

    /**
     * Return a new instance with items that satisfy a callback
     *
     * Analogous to `array_filter()`.
     *
     * @return static
     * @psalm-param callable(T): bool $callback
     */
    public function filter(callable $callback);

    /**
     * Return the first item that satisfies a callback, or false if no such item
     * is in the collection
     *
     * @return mixed|false
     * @psalm-param callable(T): bool $callback
     * @psalm-return T|false
     */
    public function find(callable $callback);

    /**
     * Return true if an item is in the collection
     *
     * @psalm-param T $item
     */
    public function has($item, bool $strict = false): bool;

    /**
     * Return the first key at which an item is found, or false if it's not in
     * the collection
     *
     * @return int|string|false
     * @psalm-param T $item
     */
    public function keyOf($item, bool $strict = false);

    /**
     * Return the first item equal but not identical to $item, or false if no
     * such item is in the collection
     *
     * @return mixed|false
     * @psalm-param T $item
     * @psalm-return T|false
     */
    public function get($item);

    /**
     * Return an array with each item
     *
     * @return mixed[]
     * @psalm-return T[]
     */
    public function toArray(bool $preserveKeys = true): array;

    /**
     * Return the first item, or false if the collection is empty
     *
     * @return mixed|false
     * @psalm-return T|false
     */
    public function first();

    /**
     * Return the last item, or false if the collection is empty
     *
     * @return mixed|false
     * @psalm-return T|false
     */
    public function last();
}
