<?php declare(strict_types=1);

namespace Lkrms\Iterator;

use IteratorIterator;
use ReturnTypeWillChange;
use Traversable;

/**
 * Iterates over an iterator, applying a callback to each value every time it is
 * returned
 *
 * @template TKey
 * @template TInput
 * @template TOutput
 *
 * @extends IteratorIterator<TKey,TOutput,Traversable<TKey,TInput>>
 */
class MapIterator extends IteratorIterator
{
    /**
     * @var callable(TInput, TKey, Traversable<TKey,TInput>): TOutput
     */
    protected $Callback;

    /**
     * @param Traversable<TKey,TInput> $iterator
     * @param callable(TInput, TKey, Traversable<TKey,TInput>): TOutput $callback
     */
    public function __construct(Traversable $iterator, callable $callback)
    {
        $this->Callback = $callback;

        parent::__construct($iterator);
    }

    /**
     * @return TOutput
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        return ($this->Callback)(parent::current(), $this->key(), $this);
    }
}
