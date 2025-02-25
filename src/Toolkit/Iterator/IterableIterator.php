<?php declare(strict_types=1);

namespace Salient\Iterator;

use Salient\Contract\Iterator\FluentIteratorInterface;
use Salient\Iterator\Concern\FluentIteratorTrait;
use ArrayIterator;
use IteratorIterator;
use Traversable;

/**
 * Iterates over an iterable
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
     * @api
     *
     * @param iterable<TKey,TValue> $iterable
     */
    final public function __construct(iterable $iterable)
    {
        parent::__construct(is_array($iterable)
            ? new ArrayIterator($iterable)
            : $iterable);
    }

    /**
     * @template T0 of array-key
     * @template T1
     *
     * @param iterable<T0,T1> $iterable
     * @return static<T0,T1>
     */
    public static function from(iterable $iterable): self
    {
        // @phpstan-ignore return.type
        return $iterable instanceof static
            ? $iterable
            : new static($iterable);
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     * @return static<int,T>
     */
    public static function fromValues(iterable $iterable): self
    {
        return new static(is_array($iterable)
            ? array_values($iterable)
            : self::generateValues($iterable));
    }

    /**
     * @template T
     *
     * @param iterable<T> $iterable
     * @return iterable<int,T>
     */
    private static function generateValues(iterable $iterable): iterable
    {
        foreach ($iterable as $value) {
            yield $value;
        }
    }
}
