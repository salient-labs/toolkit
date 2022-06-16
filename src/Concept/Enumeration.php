<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IEnumeration;

/**
 * Base class for enumerations
 */
abstract class Enumeration implements IEnumeration
{
    final private function __construct()
    {
    }
}
