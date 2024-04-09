<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Iterator\Concern\FluentIteratorTrait;
use ArrayIterator;
use IteratorIterator;
use Traversable;

/**
 * Iterates over an array or Traversable
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends IteratorIterator<TKey,TValue,Traversable<TKey,TValue>>
 * @implements FluentIteratorInterface<TKey,TValue>
 */
class IterableIterator extends IteratorIterator implements FluentIteratorInterface
{
    /** @use FluentIteratorTrait<TKey,TValue> */
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
     *
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
