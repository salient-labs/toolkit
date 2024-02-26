<?php declare(strict_types=1);

namespace Salient\Collection;

/**
 * Base class for collections of items of a given type
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements CollectionInterface<TKey,TValue>
 */
abstract class AbstractTypedCollection implements CollectionInterface
{
    /** @use CollectionTrait<TKey,TValue> */
    use CollectionTrait;

    /**
     * Clone the collection
     *
     * @return static
     */
    public function clone()
    {
        return clone $this;
    }
}
