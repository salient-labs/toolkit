<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Core\Utility\Get;
use Closure;
use IteratorIterator;
use ReturnTypeWillChange;
use Traversable;

/**
 * Iterates over an iterator, applying a callback to values as they are returned
 *
 * @api
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
     * @var Closure(TInput, TKey, Traversable<TKey,TInput>): TOutput
     */
    private Closure $Callback;

    /**
     * @param Traversable<TKey,TInput> $iterator
     * @param callable(TInput, TKey, Traversable<TKey,TInput>): TOutput $callback
     */
    public function __construct(Traversable $iterator, callable $callback)
    {
        $this->Callback = Get::closure($callback);

        parent::__construct($iterator);
    }

    /**
     * @return TOutput
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        /** @var TInput */
        $current = parent::current();
        return ($this->Callback)($current, $this->key(), $this);
    }
}
