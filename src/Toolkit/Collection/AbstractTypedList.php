<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\ListInterface;

/**
 * Base class for lists of items of a given type
 *
 * @template TValue
 *
 * @implements ListInterface<TValue>
 */
abstract class AbstractTypedList implements ListInterface
{
    /** @use ListTrait<TValue> */
    use ListTrait;

    /**
     * Clone the list
     *
     * @return static
     */
    public function clone()
    {
        return clone $this;
    }
}
