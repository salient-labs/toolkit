<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TImmutableList;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IList;

/**
 * An immutable array-like list of items
 *
 * @template TValue
 *
 * @implements IList<TValue>
 */
final class ImmutableListCollection implements IList, IImmutable
{
    /** @use TImmutableList<TValue> */
    use TImmutableList;
}
