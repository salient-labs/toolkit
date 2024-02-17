<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * Array sorting type flags
 *
 * @extends Enumeration<int>
 */
class SortTypeFlag extends Enumeration
{
    public const REGULAR = \SORT_REGULAR;
    public const NUMERIC = \SORT_NUMERIC;
    public const STRING = \SORT_STRING;
    public const LOCALE_STRING = \SORT_LOCALE_STRING;
    public const NATURAL = \SORT_NATURAL;
    public const FLAG_CASE = \SORT_FLAG_CASE;
}
