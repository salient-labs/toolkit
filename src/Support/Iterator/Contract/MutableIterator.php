<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator\Contract;

use Iterator;

/**
 * An iterator that allows the current element to be replaced
 *
 * @template TKey
 * @template TValue
 * @extends Iterator<TKey,TValue>
 */
interface MutableIterator extends Iterator
{
    /**
     * Replace the value at the current position
     *
     * @template T of TValue
     * @param T $value
     * @return T
     */
    public function replace(&$value);
}
