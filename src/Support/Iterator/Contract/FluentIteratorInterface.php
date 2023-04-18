<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator\Contract;

use Iterator;

/**
 * An iterator with a fluent interface
 *
 * @template TKey of int|string
 * @template TValue
 * @extends Iterator<TKey,TValue>
 */
interface FluentIteratorInterface extends Iterator
{
    /**
     * Convert the iterator's (remaining) elements to an array
     *
     * @return array<TKey,TValue>
     */
    public function toArray(): array;

    /**
     * Apply a callback to the iterator's (remaining) elements
     *
     * @param callable(TValue): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback);

    /**
     * Get the next element with a key or property that matches a value
     *
     * If the current element has `$value` at `$key`, it is returned after
     * moving the iterator forward.
     *
     * @param TKey $key
     * @param TValue $value
     * @return TValue|false `false` if no matching element is found.
     */
    public function nextWithValue($key, $value, bool $strict = false);
}
