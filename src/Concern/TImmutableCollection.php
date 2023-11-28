<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Implements ICollection for immutable classes
 *
 * Mutable classes should use {@see TCollection} instead.
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
    /** @use ImmutableArrayAccess<TKey,TValue> */
    use ImmutableArrayAccess {
        ImmutableArrayAccess::offsetSet insteadof TCollection;
        ImmutableArrayAccess::offsetUnset insteadof TCollection;
    }
    use Immutable;

    /**
     * @return static
     */
    protected function maybeClone()
    {
        return $this->clone();
    }
}
