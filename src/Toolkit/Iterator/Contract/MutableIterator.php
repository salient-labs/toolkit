<?php declare(strict_types=1);

namespace Salient\Iterator\Contract;

use Iterator;

/**
 * Iterates over a mutable entity while allowing the current element to be
 * replaced
 *
 * @api
 *
 * @template TKey
 * @template TValue
 *
 * @extends Iterator<TKey,TValue>
 */
interface MutableIterator extends Iterator
{
    /**
     * Replace the current element
     *
     * @param TValue $value
     * @return $this
     */
    public function replace($value);
}
