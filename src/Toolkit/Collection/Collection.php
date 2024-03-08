<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;

/**
 * An array-like collection of items
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements CollectionInterface<TKey,TValue>
 */
final class Collection implements CollectionInterface
{
    /** @use CollectionTrait<TKey,TValue> */
    use CollectionTrait;
}
