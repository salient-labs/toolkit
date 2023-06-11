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
    use HasItems, HasMutator;

    /**
     * @param T $a
     * @param T $b
     */
    protected function compareItems($a, $b): int
    {
        return $a <=> $b;
    }

    private function sortItems(bool $preserveKeys = true): void
    {
        if ($preserveKeys) {
            uasort($this->_Items, fn($a, $b) => $this->compareItems($a, $b));
        } else {
            usort($this->_Items, fn($a, $b) => $this->compareItems($a, $b));
        }
    }

    /**
     * @return static
     */
    final public function sort(bool $preserveKeys = true)
    {
        $clone = $this->mutate();
        $clone->sortItems($preserveKeys);

        return $clone;
    }

    /**
     * @return static
     */
    final public function reverse(bool $preserveKeys = true)
    {
        $clone = $this->mutate();
        $clone->_Items = array_reverse($clone->_Items, $preserveKeys);
        // clear non-numeric keys too
        if (!$preserveKeys) {
            $clone->_Items = array_values($clone->_Items);
        }

        return $clone;
    }
}
