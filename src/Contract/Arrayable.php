<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Has a toArray() method
 *
 * @template TKey of array-key
 * @template TValue
 */
interface Arrayable
{
    /**
     * Get the instance as an array
     *
     * @return array<TKey,TValue>
     */
    public function toArray(): array;
}
