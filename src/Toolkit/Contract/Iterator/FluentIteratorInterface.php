<?php declare(strict_types=1);

namespace Salient\Contract\Iterator;

use Salient\Contract\Core\Arrayable;
use Traversable;

/**
 * An iterator with a fluent interface
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends Arrayable<TKey|int,TValue>
 * @extends Traversable<TKey,TValue>
 */
interface FluentIteratorInterface extends Arrayable, Traversable
{
    /**
     * Copy the iterator's elements to an array
     *
     * @return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public function toArray(bool $preserveKeys = true): array;

    /**
     * Apply a callback to the iterator's elements
     *
     * @param callable(TValue, TKey): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback);

    /**
     * Get the first element in the iterator with a key or property equal to a
     * given value, or null if no such element is found
     *
     * @param array-key $key
     * @param mixed $value
     * @return (TValue&(object|mixed[]))|null
     */
    public function nextWithValue($key, $value, bool $strict = false);
}
