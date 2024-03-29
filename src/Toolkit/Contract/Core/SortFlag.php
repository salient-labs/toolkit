<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Salient\Core\AbstractEnumeration;

/**
 * Sorting flags
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class SortFlag extends AbstractEnumeration
{
    public const REGULAR = \SORT_REGULAR;
    public const NUMERIC = \SORT_NUMERIC;
    public const STRING = \SORT_STRING;
    public const LOCALE_STRING = \SORT_LOCALE_STRING;
    public const NATURAL = \SORT_NATURAL;
    public const FLAG_CASE = \SORT_FLAG_CASE;
}
