<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;

/**
 * A flexible array-like collection of values
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ICollection<TKey,TValue>
 */
final class Collection implements ICollection
{
    /**
     * @use TCollection<TKey,TValue>
     */
    use TCollection;
}
