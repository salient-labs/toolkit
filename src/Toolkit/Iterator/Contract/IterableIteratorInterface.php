<?php declare(strict_types=1);

namespace Salient\Iterator\Contract;

use Salient\Contract\Iterator\FluentIteratorInterface;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends FluentIteratorInterface<TKey,TValue>
 */
interface IterableIteratorInterface extends FluentIteratorInterface
{
    /**
     * @param iterable<TKey,TValue> $iterable
     */
    public function __construct(iterable $iterable);
}
