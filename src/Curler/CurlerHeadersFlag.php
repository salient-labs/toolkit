<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Concept\Enumeration;

/**
 * CurlerHeaders flags
 *
 */
final class CurlerHeadersFlag extends Enumeration
{
    /**
     * Combine headers that appear multiple times by adding commas between their
     * values, as per Section 5.2 of [RFC9110]
     *
     */
    public const COMBINE_REPEATED = 1;

    /**
     * Discard all but the last value of any headers that appear multiple times
     *
     */
    public const DISCARD_REPEATED = 2;

    /**
     * Sort headers to maintain the position of their last appearance
     */
    public const SORT_BY_LAST = 4;

}
