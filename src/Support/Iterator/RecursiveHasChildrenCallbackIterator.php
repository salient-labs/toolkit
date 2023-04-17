<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator;

use IteratorIterator;
use RecursiveIterator;

/**
 * Uses a callback to accept or reject a recursive iterator's entries for
 * recursion
 *
 * Similar to `RecursiveCallbackFilterIterator`, but the callback is used to
 * filter the return value of `RecursiveIterator::hasChildren()`, allowing
 * values to be treated as leaf nodes even if they have children.
 *
 * @template TKey
 * @template TValue
 * @extends IteratorIterator<TKey,TValue,RecursiveIterator<TKey,TValue>>
 * @implements RecursiveIterator<TKey,TValue>
 */
class RecursiveHasChildrenCallbackIterator extends IteratorIterator implements RecursiveIterator
{
    /**
     * @var RecursiveIterator<TKey,TValue>
     */
    protected $Iterator;

    /**
     * @var callable(TValue, TKey, RecursiveIterator<TKey,TValue>): bool
     */
    private $Callback;

    /**
     * @param RecursiveIterator<TKey,TValue> $iterator
     * @param callable(TValue, TKey, RecursiveIterator<TKey,TValue>): bool $callback
     */
    public function __construct(RecursiveIterator $iterator, callable $callback)
    {
        $this->Iterator = $iterator;
        $this->Callback = $callback;

        parent::__construct($iterator);
    }

    public function hasChildren(): bool
    {
        if (!$this->Iterator->hasChildren()) {
            return false;
        }

        $key = $this->Iterator->key();
        $current = $this->Iterator->current();

        return ($this->Callback)($current, $key, $this->Iterator);
    }

    /**
     * @return RecursiveIterator<TKey,TValue>|null
     */
    public function getChildren(): ?RecursiveIterator
    {
        return $this->Iterator->getChildren();
    }
}
