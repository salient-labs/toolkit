<?php declare(strict_types=1);

namespace Lkrms\Iterator\Contract;

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
     * @param TValue $value
     * @return $this
     */
    public function replace($value);
}
