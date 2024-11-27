<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements CollectionInterface<TKey,TValue>
 */
class Collection implements CollectionInterface
{
    /** @use CollectionTrait<TKey,TValue> */
    use CollectionTrait;
}
