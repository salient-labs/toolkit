<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Implements IList for immutable classes
 *
 * Mutable classes should use {@see TList} instead.
 *
 * @template TValue
 *
 * @see \Lkrms\Contract\IList
 */
trait TImmutableList
{
    /** @use TList<TValue> */
    use TList;
    /** @use ImmutableArrayAccess<int,TValue> */
    use ImmutableArrayAccess {
        ImmutableArrayAccess::offsetSet insteadof TList;
        ImmutableArrayAccess::offsetUnset insteadof TList;
    }
    use Immutable {
        Immutable::clone insteadof TList;
    }
}
