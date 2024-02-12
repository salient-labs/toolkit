<?php declare(strict_types=1);

namespace Lkrms\Iterator;

use Lkrms\Iterator\Contract\RecursiveCallbackIteratorInterface;
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
 * @template TKey
 * @template TValue
 *
 * @extends IteratorIterator<TKey,TValue,RecursiveIterator<TKey,TValue>>
 * @implements RecursiveCallbackIteratorInterface<TKey,TValue>
 */
class RecursiveCallbackIterator extends IteratorIterator implements RecursiveCallbackIteratorInterface
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

        return ($this->Callback)(
            $this->Iterator->current(),
            $this->Iterator->key(),
            $this->Iterator,
        );
    }

    /**
     * @return static|null
     */
    public function getChildren(): ?self
    {
        /** @var RecursiveIterator<TKey,TValue>|null */
        $children = $this->Iterator->getChildren();

        return $children === null
            // @codeCoverageIgnoreStart
            ? null
            // @codeCoverageIgnoreEnd
            : new static($children, $this->Callback);
    }
}
