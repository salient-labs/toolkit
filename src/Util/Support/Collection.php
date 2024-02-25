<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;
use Salient\Core\Contract\Arrayable;

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
