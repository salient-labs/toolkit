<?php declare(strict_types=1);

namespace Salient\Iterator\Contract;

use Salient\Core\Contract\Arrayable;
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
     * Get the value of the iterator's first element with a key or property
     * equal to a given value
     *
     * @param array-key $key
     * @param mixed $value
     * @return TValue|null `null` if no matching element is found.
     */
    public function nextWithValue($key, $value, bool $strict = false);
}
