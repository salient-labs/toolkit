<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TImmutableList;
use Lkrms\Contract\IList;
use Salient\Core\Contract\Immutable;

/**
 * An immutable array-like list of items
 *
 * @template TValue
 *
 * @implements IList<TValue>
 */
final class ImmutableListCollection implements IList, Immutable
{
    /** @use TImmutableList<TValue> */
    use TImmutableList;
}
