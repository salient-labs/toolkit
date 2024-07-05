<?php declare(strict_types=1);

namespace Salient\Curler\Pager;

use Salient\Utility\Arr;
use Closure;

trait HasEntitySelector
{
    /** @var Closure(mixed): list<mixed> */
    private Closure $EntitySelector;

    /**
     * @param (Closure(mixed): list<mixed>)|array-key|null $entitySelector
     */
    private function applyEntitySelector($entitySelector): void
    {
        $this->EntitySelector = $entitySelector instanceof Closure
            ? $entitySelector
            : ($entitySelector === null
                ? fn($data) => Arr::wrapList($data)
                : fn($data) => Arr::wrapList(Arr::get((string) $entitySelector, $data)));
    }
}
