<?php declare(strict_types=1);

namespace Salient\Contract\Iterator;

use Salient\Contract\Core\Arrayable;
use Traversable;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends Arrayable<TKey,TValue>
 * @extends Traversable<TKey,TValue>
 */
interface FluentIteratorInterface extends Arrayable, Traversable
{
    /**
     * Get the iterator's elements as an array
     */
    public function toArray(bool $preserveKeys = true): array;

    /**
     * Get the first element in the iterator with the given value at the given
     * key, or null if no such element is found
     *
     * @param array-key $key
     * @param mixed $value
     * @return (TValue&(object|mixed[]))|null
     */
    public function getFirstWith($key, $value, bool $strict = false);
}
