<?php declare(strict_types=1);

namespace Lkrms\Iterator;

use Lkrms\Iterator\Concern\FluentIteratorTrait;
use Lkrms\Iterator\Contract\FluentIteratorInterface;
use Iterator;
use IteratorIterator;

/**
 * Iterates over an iterator, providing a fluent interface to its elements
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends IteratorIterator<TKey,TValue,Iterator<TKey,TValue>>
 *
 * @implements FluentIteratorInterface<TKey,TValue>
 */
class FluentIterator extends IteratorIterator implements FluentIteratorInterface
{
    /** @use FluentIteratorTrait<TKey,TValue> */
    use FluentIteratorTrait;

    /**
     * @template T0 of array-key
     * @template T1
     *
     * @param Iterator<T0,T1> $iterator
     *
     * @return self<T0,T1>
     */
    public static function from(Iterator $iterator): self
    {
        if ($iterator instanceof self) {
            return $iterator;
        }

        return new self($iterator);
    }
}
