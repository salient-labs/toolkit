<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * An iterator with a fluent interface
 *
 * @template TKey
 * @template TValue
 * @extends \Iterator<TKey,TValue>
 */
interface FluentIterator extends \Iterator
{
    /**
     * Move to the next element with a key or property that matches a value and
     * return it
     *
     * The current element is returned if it is a match.
     *
     * After calling {@see FluentIterator::nextWithValue()}, the element
     * subsequent to the returned element is the iterator's current element.
     *
     * @return TValue|false `false` if no matching element was found.
     */
    public function nextWithValue($key, $value, bool $strict = false);
}
