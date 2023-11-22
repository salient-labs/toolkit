<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TImmutableCollection;
use Lkrms\Contract\Arrayable;
use Lkrms\Contract\ICollection;
use Lkrms\Contract\IImmutable;

/**
 * An immutable array-like collection of items
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ICollection<TKey,TValue>
 * @implements Arrayable<TKey,TValue>
 */
final class ImmutableCollection implements ICollection, Arrayable, IImmutable
{
    /** @use TImmutableCollection<TKey,TValue> */
    use TImmutableCollection;
}
