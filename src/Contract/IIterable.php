<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * An iterable iterator with a fluent interface
 *
 * @template TValue
 * @extends \Iterator<int,TValue>
 */
interface IIterable extends \Iterator
{
    /**
     * Get the next element with a key or property that matches a value
     *
     * If the current element has `$value` at `$key`, it is returned after
     * moving the iterator forward.
     *
     * @return TValue|false `false` if no matching element is found.
     */
    public function nextWithValue($key, $value, bool $strict = false);
}
