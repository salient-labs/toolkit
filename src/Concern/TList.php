<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Implements IList
 *
 * Unless otherwise noted, {@see TList} methods operate on one instance of the
 * class. Immutable classes should use {@see TImmutableList} instead.
 *
 * @template TValue
 *
 * @see \Lkrms\Contract\IList
 */
trait TList
{
    /** @use TCollection<int,TValue> */
    use TCollection;

    /**
     * @param TValue ...$item
     * @return static
     */
    public function push(...$item)
    {
        if (!$item) {
            return $this;
        }
        $clone = $this->clone();
        array_push($clone->Items, ...$item);
        return $clone;
    }

    /**
     * @param TValue ...$item
     * @return static
     */
    public function unshift(...$item)
    {
        if (!$item) {
            return $this;
        }
        $clone = $this->clone();
        array_unshift($clone->Items, ...$item);
        return $clone;
    }
}
