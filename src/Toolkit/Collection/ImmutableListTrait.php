<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IList;
use Salient\Core\Concern\HasImmutableProperties;

/**
 * Implements IList for immutable classes
 *
 * Mutable classes should use {@see TList} instead.
 *
 * @template TValue
 *
 * @see IList
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
    use HasImmutableProperties;

    /**
     * @return static
     */
    protected function maybeClone()
    {
        return $this->clone();
    }
}
