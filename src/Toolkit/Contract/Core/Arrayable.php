<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 */
interface Arrayable
{
    /**
     * Get the object as an array
     *
     * @return array<TKey,TValue>
     */
    public function toArray(): array;
}
