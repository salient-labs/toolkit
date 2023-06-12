<?php declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Concept\Enumeration;

/**
 * ICurlerHeaders flags
 *
 * @extends Enumeration<int>
 */
final class CurlerHeadersFlag extends Enumeration
{
    /**
     * Combine headers given multiple times by adding commas between their
     * values, as per Section 5.2 of [RFC9110]
     *
     */
    public const COMBINE = 1;

    /**
     * Discard all but the last value of any headers given multiple times
     *
     */
    public const KEEP_LAST = 2;

    /**
     * Discard all but the first value of any headers given multiple times
     *
     */
    public const KEEP_FIRST = 4;
}
