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
     * @return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public function toArray(bool $preserveKeys = true): array;
}
