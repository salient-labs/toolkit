<?php

declare(strict_types=1);

namespace Lkrms\Core;

use Lkrms\Core\Contract\IEnumeration;

/**
 * Base class for enumerations
 */
abstract class Enumeration implements IEnumeration
{
    final private function __construct()
    {
    }
}
