<?php declare(strict_types=1);

namespace Salient\Iterator;

use IteratorIterator;
use ReturnTypeWillChange;
use Traversable;

/**
 * Iterates over an iterator, applying a callback to each value returned
 *
 * @api
 *
 * @template TKey
 * @template TValue
 * @template TIterator of Traversable<TKey,TValue>
 * @template TReturn of TValue
 *
 * @extends IteratorIterator<TKey,TValue,TIterator>
 */
class MapIterator extends IteratorIterator
{
    /** @var TIterator */
    private Traversable $Iterator;
    /** @var callable(TValue, TKey, TIterator): TReturn */
    private $Callback;

    /**
     * @api
     *
     * @param TIterator $iterator
     * @param callable(TValue, TKey, TIterator): TReturn $callback
     */
    public function __construct(Traversable $iterator, callable $callback)
    {
        $this->Iterator = $iterator;
        $this->Callback = $callback;
        parent::__construct($iterator);
    }

    /**
     * @return TReturn
     * @disregard P1038
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        return ($this->Callback)(
            parent::current(),
            $this->key(),
            $this->Iterator,
        );
    }
}
