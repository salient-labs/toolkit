<?php

declare(strict_types=1);

namespace Lkrms\Concern;

trait HasSortableItems
{
    use HasItems;

    protected function compareItems($a, $b): int
    {
        return $a <=> $b;
    }

    private function sortItems(): void
    {
        usort($this->Items, fn($a, $b) => $this->compareItems($a, $b));
    }

    /**
     * @return $this
     */
    final public function sort()
    {
        $this->sortItems();
        return $this;
    }

}
