<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IConvertibleEnumeration;

/**
 * Base class for enumerations that convert the values of their public constants
 * to and from their names
 */
abstract class ConvertibleEnumeration extends Enumeration implements IConvertibleEnumeration
{
}
