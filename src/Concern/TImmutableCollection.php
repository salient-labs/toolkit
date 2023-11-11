<?php declare(strict_types=1);

namespace Lkrms\Concern;

use LogicException;

/**
 * Implements ICollection to provide a strictly immutable collection of values
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @see \Lkrms\Contract\ICollection
 */
trait TImmutableCollection
{
    /** @use TCollection<TKey,TValue> */
    use TCollection;

    /**
     * @return TValue|false
     */
    public function pop()
    {
        $this->throwImmutableCollectionException();
    }

    /**
     * @return TValue|false
     */
    public function shift()
    {
        $this->throwImmutableCollectionException();
    }

    // Implementation of `ArrayAccess`:

    /**
     * @param TKey|null $offset
     * @param TValue $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->throwImmutableCollectionException();
    }

    /**
     * @param TKey $offset
     */
    public function offsetUnset($offset): void
    {
        $this->throwImmutableCollectionException();
    }

    /**
     * @return never
     */
    private function throwImmutableCollectionException()
    {
        throw new LogicException(sprintf('Items in %s are immutable', static::class));
    }
}
