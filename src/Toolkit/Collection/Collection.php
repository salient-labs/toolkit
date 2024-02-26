<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Core\Contract\Arrayable;

/**
 * An array-like collection of items
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements CollectionInterface<TKey,TValue>
 * @implements Arrayable<TKey,TValue>
 */
final class Collection implements CollectionInterface, Arrayable
{
    /** @use CollectionTrait<TKey,TValue> */
    use CollectionTrait;
}
