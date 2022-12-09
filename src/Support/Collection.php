<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\HasSortableItems;
use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;
use Lkrms\Contract\IComparable;
use UnexpectedValueException;

/**
 * An array-like collection of values
 *
 * @template T
 * @implements ICollection<T>
 */
final class Collection implements ICollection
{
    /**
     * @use TCollection<T>
     */
    use TCollection;

}
