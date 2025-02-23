<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;
use IteratorAggregate;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements CollectionInterface<TKey,TValue>
 * @implements IteratorAggregate<TKey,TValue>
 */
class Collection implements CollectionInterface, IteratorAggregate
{
    /** @use CollectionTrait<TKey,TValue,static<TKey|int,TValue>> */
    use CollectionTrait;
}
