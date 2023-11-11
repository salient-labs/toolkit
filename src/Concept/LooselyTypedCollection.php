<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;
use Lkrms\Contract\IImmutable;

/**
 * Base class for collections of objects of an unenforced type
 *
 * Extend {@see TypedCollection} instead if type safety concerns outweigh
 * performance.
 *
 * @template TKey of array-key
 * @template TValue of object
 *
 * @implements ICollection<TKey,TValue>
 */
abstract class LooselyTypedCollection implements ICollection, IImmutable
{
    /** @use TCollection<TKey,TValue> */
    use TCollection;
}
