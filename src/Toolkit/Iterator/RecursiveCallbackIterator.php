<?php declare(strict_types=1);

namespace Salient\Iterator;

use IteratorIterator;
use RecursiveCallbackFilterIterator;
use RecursiveIterator;

/**
 * Iterates over a recursive iterator, using a callback to determine which
 * elements to descend into
 *
 * Similar to {@see RecursiveCallbackFilterIterator}, but the callback is only
 * used to filter the return value of {@see RecursiveIterator::hasChildren()}.
 * This allows elements to be treated as leaf nodes even if they have children.
 *
 * @api
 *
 * @template TKey
 * @template TValue
 * @template TIterator of RecursiveIterator<TKey,TValue>
 *
 * @extends IteratorIterator<TKey,TValue,TIterator>
 * @implements RecursiveIterator<TKey,TValue>
 */
class RecursiveCallbackIterator extends IteratorIterator implements RecursiveIterator
{
    /** @var TIterator */
    private RecursiveIterator $Iterator;
    /** @var callable(TValue, TKey, TIterator): bool */
    private $Callback;

    /**
     * @api
     *
     * @param TIterator $iterator
     * @param callable(TValue, TKey, TIterator): bool $callback
     */
    final public function __construct(RecursiveIterator $iterator, callable $callback)
    {
        $this->Iterator = $iterator;
        $this->Callback = $callback;
        parent::__construct($iterator);
    }

    /**
     * @inheritDoc
     */
    public function hasChildren(): bool
    {
        return $this->Iterator->hasChildren()
            && ($this->Callback)(
                $this->Iterator->current(),
                $this->Iterator->key(),
                $this->Iterator,
            );
    }

    /**
     * @return static<TKey,TValue,TIterator>|null
     */
    public function getChildren(): ?self
    {
        /** @var TIterator|null */
        $children = $this->Iterator->getChildren();
        return $children === null
            ? null
            : new static($children, $this->Callback);
    }
}
