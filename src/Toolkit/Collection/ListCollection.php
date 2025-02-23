<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;
use IteratorAggregate;

/**
 * @api
 *
 * @template TValue
 *
 * @implements CollectionInterface<int,TValue>
 * @implements IteratorAggregate<int,TValue>
 */
class ListCollection implements CollectionInterface, IteratorAggregate
{
    /** @use ListCollectionTrait<int,TValue,static<TValue>> */
    use ListCollectionTrait;
}
