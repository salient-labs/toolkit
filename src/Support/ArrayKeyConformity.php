<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concept\Enumeration;

/**
 * Array key conformity levels
 *
 */
final class ArrayKeyConformity extends Enumeration
{
    /**
     * Arrays may have different keys in different orders
     */
    public const NONE = 0;

    /**
     * Data arrays always have the same keys in the same order, but key maps (if
     * applicable) may not have the same signature as data arrays
     */
    public const PARTIAL = 1;

    /**
     * Arrays and key maps (if applicable) always have the same keys in the same
     * order
     */
    public const COMPLETE = 2;

}
