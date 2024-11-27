<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;

/**
 * @api
 *
 * @template TValue
 *
 * @implements CollectionInterface<int,TValue>
 */
class ListCollection implements CollectionInterface
{
    /** @use ListCollectionTrait<TValue> */
    use ListCollectionTrait;
}
