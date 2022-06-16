<?php

declare(strict_types=1);

namespace Lkrms\Concern;

trait TSortable
{
    use HasItems;

    protected function compareItems($a, $b): int
    {
        return $a == $b ? 0 : ($a < $b ? -1 : 1);
    }

    private function sortItems(): void
    {
        usort($this->Items, fn($a, $b) => $this->compareItems($a, $b));
    }

    /**
     * Get a sorted copy of the object
     *
     * @return static
     */
    public function sort()
    {
        $copy = clone $this;
        $copy->sortItems();
        return $copy;
    }

}
