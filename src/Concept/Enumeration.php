<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IEnumeration;

/**
 * Base class for enumerations
 *
 * @template TValue
 *
 * @implements IEnumeration<TValue>
 */
abstract class Enumeration implements IEnumeration
{
    final private function __construct()
    {
    }
}
