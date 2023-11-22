<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\TList;
use Lkrms\Contract\IList;

/**
 * Base class for lists of items of a given type
 *
 * @template TValue
 *
 * @implements IList<TValue>
 */
abstract class TypedList implements IList
{
    /** @use TList<TValue> */
    use TList;
}
