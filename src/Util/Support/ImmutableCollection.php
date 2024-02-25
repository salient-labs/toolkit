<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TImmutableCollection;
use Lkrms\Contract\ICollection;
use Salient\Core\Contract\Arrayable;
use Salient\Core\Contract\Immutable;

/**
 * An immutable array-like collection of items
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ICollection<TKey,TValue>
 * @implements Arrayable<TKey,TValue>
 */
final class ImmutableCollection implements ICollection, Arrayable, Immutable
{
    /** @use TImmutableCollection<TKey,TValue> */
    use TImmutableCollection;
}
