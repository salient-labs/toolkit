<?php declare(strict_types=1);

namespace Lkrms\Iterator\Contract;

use Iterator;

/**
 * An iterator with a fluent interface
 *
 * @template TKey of array-key
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
     * Apply a callback to the iterator's (remaining) elements until the
     * iterator is empty or the callback returns a value other than true
     *
     * @param callable(TValue): bool $callback Return `false` to cancel the
     * operation.
     * @param bool|null $result Receives `false` if the operation is cancelled
     * by the callback, `true` otherwise.
     * @return $this
     */
    public function forEachWhileTrue(callable $callback, ?bool &$result = null);

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
