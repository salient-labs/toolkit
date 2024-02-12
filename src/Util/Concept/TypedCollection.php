<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;

/**
 * Base class for collections of items of a given type
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ICollection<TKey,TValue>
 */
abstract class TypedCollection implements ICollection
{
    /** @use TCollection<TKey,TValue> */
    use TCollection;

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
