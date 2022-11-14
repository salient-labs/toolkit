<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Compares itself with others
 *
 */
interface IComparable
{
    /**
     * @param static $a
     * @param static $b
     */
    public static function compare($a, $b, bool $strict = false): int;

}
