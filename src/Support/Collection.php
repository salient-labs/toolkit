<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;

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
