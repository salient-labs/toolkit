<?php declare(strict_types=1);

namespace Lkrms\Iterator\Contract;

use Lkrms\Contract\Arrayable;
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
     * Copy the elements of the iterator to an array
     *
     * @return array<TKey,TValue>|list<TValue>
     * @phpstan-return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public function toArray(bool $preserveKeys = true): array;

    /**
     * Apply a callback to the elements of the iterator
     *
     * @param callable(TValue): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback);

    /**
     * Apply a callback to the elements of the iterator until cancelled by the
     * callback
     *
     * @param callable(TValue): (true|mixed) $callback Return `true` to continue
     * iterating over the iterator.
     * @param bool|null $result If `$result` is provided, `false` is assigned if
     * iteration is cancelled by the callback, otherwise `true` is assigned.
     * @return $this
     */
    public function forEachWhile(callable $callback, ?bool &$result = null);

    /**
     * Get the next element with a key or property that matches a value
     *
     * If the current element has `$value` at `$key`, it is returned after
     * moving the iterator forward.
     *
     * @param array-key $key
     * @param mixed $value
     * @return TValue|false `false` if no matching element is found.
     */
    public function nextWithValue($key, $value, bool $strict = false);
}
