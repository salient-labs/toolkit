<?php declare(strict_types=1);

namespace Salient\Curler\Pager;

use Salient\Core\Utility\Arr;
use Closure;

trait HasEntitySelector
{
    /**
     * @var Closure(mixed): list<mixed>
     */
    private Closure $EntitySelector;

    /**
     * @param (Closure(mixed): list<mixed>)|array-key|null $entitySelector
     */
    private function applyEntitySelector($entitySelector): void
    {
        $this->EntitySelector = $entitySelector instanceof Closure
            ? $entitySelector
            : ($entitySelector === null
                ? fn($data) => Arr::listWrap($data)
                : fn($data) => Arr::listWrap(Arr::get((string) $entitySelector, $data)));
    }
}
