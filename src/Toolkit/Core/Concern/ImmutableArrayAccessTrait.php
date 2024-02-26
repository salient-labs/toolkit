<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Core\Exception\BadMethodCallException;

/**
 * @template TKey of array-key
 * @template TValue
 */
trait ImmutableArrayAccessTrait
{
    /**
     * @internal
     *
     * @param TKey|null $offset
     * @param TValue $value
     * @return never
     */
    public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException(sprintf(
            '%s values are read-only',
            static::class,
        ));
    }

    /**
     * @internal
     *
     * @param TKey $offset
     * @return never
     */
    public function offsetUnset($offset): void
    {
        throw new BadMethodCallException(sprintf(
            '%s values are read-only',
            static::class,
        ));
    }
}
