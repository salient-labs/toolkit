<?php declare(strict_types=1);

namespace Salient\Contract\Collection;

use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Jsonable;
use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * An array-like collection of items
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends ArrayAccess<TKey,TValue>
 * @extends Arrayable<TKey,TValue|mixed[]>
 * @extends IteratorAggregate<TKey,TValue>
 */
interface CollectionInterface extends
    ArrayAccess,
    Arrayable,
    Countable,
    IteratorAggregate,
    JsonSerializable,
    Jsonable
{
    /**
     * Pass the value of each item to the callback
     */
    public const CALLBACK_USE_VALUE = 1;

    /**
     * Pass the key of each item to the callback
     */
    public const CALLBACK_USE_KEY = 2;

    /**
     * Pass a key-value pair to the callback for each item
     */
    public const CALLBACK_USE_BOTH = 3;

    /**
     * Return the first item that satisfies the callback
     */
    public const FIND_VALUE = 4;

    /**
     * Return the key of the first item that satisfies the callback
     */
    public const FIND_KEY = 8;

    /** @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $items */
    public function __construct($items = []);

    /**
     * Get a new collection with no items
     *
     * @return static
     */
    public static function empty();

    /**
     * Get a copy of the collection
     *
     * @return static
     */
    public function copy();

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
     * @return static
     */
    public function unset($key);

    /**
     * Merge the collection with the given items
     *
     * @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $items
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
     * @template T of TValue|TKey|array{TKey,TValue}
     *
     * @param callable(T, T|null $next, T|null $prev): mixed $callback
     * @param int-mask-of<CollectionInterface::*> $mode
     * @return $this
     */
    public function forEach(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE);

    /**
     * Reduce the collection to items that satisfy a callback
     *
     * @template T of TValue|TKey|array{TKey,TValue}
     *
     * @param callable(T, T|null $next, T|null $prev): bool $callback
     * @param int-mask-of<CollectionInterface::*> $mode
     * @return static
     */
    public function filter(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE);

    /**
     * Get the first item that satisfies a callback, or null if there is no such
     * item in the collection
     *
     * @template T of TValue|TKey|array{TKey,TValue}
     *
     * @param callable(T, T|null $next, T|null $prev): bool $callback
     * @param int-mask-of<CollectionInterface::*> $mode
     * @return ($mode is 8|9|10|11 ? TKey : TValue)|null
     */
    public function find(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE | CollectionInterface::FIND_VALUE);

    /**
     * Reduce the collection to items with keys in an array
     *
     * @param TKey[] $keys
     * @return static
     */
    public function only(array $keys);

    /**
     * Reduce the collection to items with keys in an index
     *
     * @param array<TKey,true> $index
     * @return static
     */
    public function onlyIn(array $index);

    /**
     * Reduce the collection to items with keys not in an array
     *
     * @param TKey[] $keys
     * @return static
     */
    public function except(array $keys);

    /**
     * Reduce the collection to items with keys not in an index
     *
     * @param array<TKey,true> $index
     * @return static
     */
    public function exceptIn(array $index);

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
