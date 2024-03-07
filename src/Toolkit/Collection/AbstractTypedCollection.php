<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;

/**
 * Base class for collections of items of a given type
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements CollectionInterface<TKey,TValue>
 */
abstract class AbstractTypedCollection implements CollectionInterface
{
    /** @use CollectionTrait<TKey,TValue> */
    use CollectionTrait;
}
