<?php declare(strict_types=1);

namespace Lkrms\Concern;

use LogicException;

/**
 * @template TKey of array-key
 * @template TValue
 */
trait ImmutableArrayAccess
{
    /**
     * @param TKey|null $offset
     * @param TValue $value
     */
    public function offsetSet($offset, $value): void
    {
        throw new LogicException(sprintf('%s is immutable', static::class));
    }

    /**
     * @param TKey $offset
     */
    public function offsetUnset($offset): void
    {
        throw new LogicException(sprintf('%s is immutable', static::class));
    }
}
