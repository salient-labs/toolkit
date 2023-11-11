<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TImmutableCollection;
use Lkrms\Contract\ICollection;
use Lkrms\Contract\IImmutable;

/**
 * A strictly immutable collection of values
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ICollection<TKey,TValue>
 */
final class ImmutableCollection implements ICollection, IImmutable
{
    /** @use TImmutableCollection<TKey,TValue> */
    use TImmutableCollection;
}
