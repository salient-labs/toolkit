<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Immutable;

/**
 * An immutable array-like collection of items
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements CollectionInterface<TKey,TValue>
 */
final class ImmutableCollection implements CollectionInterface, Immutable
{
    /** @use ImmutableCollectionTrait<TKey,TValue> */
    use ImmutableCollectionTrait;
}
