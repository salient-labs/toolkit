<?php declare(strict_types=1);

namespace Lkrms\Support\Iterator;

use ArrayIterator;
use IteratorIterator;
use Lkrms\Support\Iterator\Concern\FluentIteratorTrait;
use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Traversable;

/**
 * Iterates over an iterable
 *
 * @template TKey of array-key
 * @template TValue
 * @extends IteratorIterator<TKey,TValue,Traversable<TKey,TValue>>
 * @implements FluentIteratorInterface<TKey,TValue>
 */
final class IterableIterator extends IteratorIterator implements FluentIteratorInterface
{
    /**
     * @use FluentIteratorTrait<TKey,TValue>
     */
    use FluentIteratorTrait;

    /**
     * @param iterable<TKey,TValue> $iterable
     */
    public function __construct(iterable $iterable)
    {
        if (is_array($iterable)) {
            $iterable = new ArrayIterator($iterable);
        }

        parent::__construct($iterable);
    }

    /**
     * @template T0 of array-key
     * @template T1
     * @param iterable<T0,T1> $iterable
     * @return self<T0,T1>
     */
    public static function from(iterable $iterable): self
    {
        if ($iterable instanceof self) {
            return $iterable;
        }

        return new self($iterable);
    }
}
