<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\ICollection;
use Salient\Core\Concern\HasImmutableProperties;

/**
 * Implements ICollection for immutable classes
 *
 * Mutable classes should use {@see TCollection} instead.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @see ICollection
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
    use HasImmutableProperties;

    /**
     * @return static
     */
    protected function maybeClone()
    {
        return $this->clone();
    }
}
