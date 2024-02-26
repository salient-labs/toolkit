<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Concern\ImmutableArrayAccessTrait;

/**
 * Implements ListInterface for immutable classes
 *
 * Mutable classes should use {@see TList} instead.
 *
 * @template TValue
 *
 * @see ListInterface
 */
trait ImmutableListTrait
{
    /** @use ListTrait<TValue> */
    use ListTrait;
    /** @use ImmutableArrayAccessTrait<int,TValue> */
    use ImmutableArrayAccessTrait {
        ImmutableArrayAccessTrait::offsetSet insteadof ListTrait;
        ImmutableArrayAccessTrait::offsetUnset insteadof ListTrait;
    }
    use HasImmutableProperties;

    /**
     * @return static
     */
    protected function maybeClone()
    {
        return $this->clone();
    }
}
