<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use ArrayAccess;
use LogicException;

/**
 * Implements ArrayAccess setters for immutable objects
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-require-implements ArrayAccess
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
        throw new LogicException(sprintf(
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
        throw new LogicException(sprintf(
            '%s values are read-only',
            static::class,
        ));
    }
}
