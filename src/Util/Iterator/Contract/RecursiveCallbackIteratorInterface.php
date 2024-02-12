<?php declare(strict_types=1);

namespace Lkrms\Iterator\Contract;

use RecursiveIterator;

/**
 * @template TKey
 * @template TValue
 *
 * @extends RecursiveIterator<TKey,TValue>
 */
interface RecursiveCallbackIteratorInterface extends RecursiveIterator
{
    /**
     * @param RecursiveIterator<TKey,TValue> $iterator
     * @param callable(TValue, TKey, RecursiveIterator<TKey,TValue>): bool $callback
     */
    public function __construct(RecursiveIterator $iterator, callable $callback);
}
