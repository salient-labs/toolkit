<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TList;
use Lkrms\Contract\IList;

/**
 * An array-like list of items
 *
 * @template TValue
 *
 * @implements IList<TValue>
 */
final class ListCollection implements IList
{
    /** @use TList<TValue> */
    use TList;
}
