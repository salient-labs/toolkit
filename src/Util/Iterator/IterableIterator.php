<?php declare(strict_types=1);

namespace Lkrms\Iterator;

use Lkrms\Iterator\Concern\FluentIteratorTrait;
use Lkrms\Iterator\Contract\IterableIteratorInterface;
use ArrayIterator;
use IteratorIterator;
use Traversable;

/**
 * Iterates over an array or Traversable, providing a fluent interface to its
 * elements
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends IteratorIterator<TKey,TValue,Traversable<TKey,TValue>>
 * @implements IterableIteratorInterface<TKey,TValue>
 */
class IterableIterator extends IteratorIterator implements IterableIteratorInterface
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
     * @param iterable<TKey,TValue> $iterable
     * @return static
     */
    public static function from(iterable $iterable): self
    {
        if ($iterable instanceof static) {
            return $iterable;
        }

        return new static($iterable);
    }
}
