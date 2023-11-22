<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TCollection;
use Lkrms\Contract\Arrayable;
use Lkrms\Contract\ICollection;

/**
 * An array-like collection of items
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ICollection<TKey,TValue>
 * @implements Arrayable<TKey,TValue>
 */
final class Collection implements ICollection, Arrayable
{
    /** @use TCollection<TKey,TValue> */
    use TCollection;
}
