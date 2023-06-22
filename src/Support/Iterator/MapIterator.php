<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator;

use IteratorIterator;
use ReturnTypeWillChange;
use Traversable;

/**
 * Applies a callback to each value as it is returned
 *
 * @template TKey of array-key
 * @template TInput
 * @template TOutput
 * @extends IteratorIterator<TKey,TOutput,Traversable<TKey,TInput>>
 */
class MapIterator extends IteratorIterator
{
    /**
     * @var callable(TInput): TOutput
     */
    protected $Callback;

    /**
     * @param Traversable<TKey,TInput> $iterator
     * @param callable(TInput): TOutput $callback
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
        return ($this->Callback)(parent::current());
    }
}
