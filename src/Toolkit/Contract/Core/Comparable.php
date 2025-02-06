<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface Comparable
{
    /**
     * Get an integer less than, equal to, or greater than zero when $a is less
     * than, equal to, or greater than $b, respectively
     *
     * @param static $a
     * @param static $b
     * @return int A value that can be used instead of `$a <=> $b`.
     */
    public static function compare($a, $b): int;
}
