<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * @template T
 */
trait HasSortableItems
{
    /**
     * @use HasItems<T>
     */
    use HasItems;

    /**
     * @param T $a
     * @param T $b
     */
    protected function compareItems($a, $b): int
    {
        return $a <=> $b;
    }

    private function sortItems(): void
    {
        usort($this->_Items, fn($a, $b) => $this->compareItems($a, $b));
    }

    /**
     * @return $this
     */
    final public function sort()
    {
        $clone = clone $this;
        $clone->sortItems();

        return $clone;
    }

    /**
     * @return $this
     */
    final public function reverse()
    {
        $clone         = clone $this;
        $clone->_Items = array_reverse($clone->_Items);

        return $clone;
    }
}
