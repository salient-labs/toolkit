<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Utility\Get;
use Closure;
use IteratorIterator;
use RecursiveCallbackFilterIterator;
use RecursiveIterator;

/**
 * Iterates over a recursive iterator, using a callback to determine which items
 * with children to descend into
 *
 * Similar to {@see RecursiveCallbackFilterIterator}, but the callback is only
 * used to filter the return value of {@see RecursiveIterator::hasChildren()},
 * allowing values to be treated as leaf nodes even if they have children.
 *
 * @api
 *
 * @template TKey
 * @template TValue
 *
 * @extends IteratorIterator<TKey,TValue,RecursiveIterator<TKey,TValue>>
 * @implements RecursiveIterator<TKey,TValue>
 */
class RecursiveCallbackIterator extends IteratorIterator implements RecursiveIterator
{
    /** @var RecursiveIterator<TKey,TValue> */
    private RecursiveIterator $Iterator;
    /** @var Closure(TValue, TKey, RecursiveIterator<TKey,TValue>): bool */
    private Closure $Callback;

    /**
     * @param RecursiveIterator<TKey,TValue> $iterator
     * @param callable(TValue, TKey, RecursiveIterator<TKey,TValue>): bool $callback
     */
    public function __construct(RecursiveIterator $iterator, callable $callback)
    {
        $this->Iterator = $iterator;
        $this->Callback = Get::closure($callback);

        parent::__construct($iterator);
    }

    /**
     * @inheritDoc
     */
    public function hasChildren(): bool
    {
        if (!$this->Iterator->hasChildren()) {
            return false;
        }

        return ($this->Callback)(
            $this->Iterator->current(),
            $this->Iterator->key(),
            $this->Iterator,
        );
    }

    /**
     * @return self<TKey,TValue>|null
     */
    public function getChildren(): ?self
    {
        /** @var RecursiveIterator<TKey,TValue>|null */
        $children = $this->Iterator->getChildren();

        return $children === null
            // @codeCoverageIgnoreStart
            ? null
            // @codeCoverageIgnoreEnd
            : new self($children, $this->Callback);
    }
}
