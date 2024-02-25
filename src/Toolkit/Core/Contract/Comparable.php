<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Able to compare instances of itself, e.g. for sorting purposes
 */
interface Comparable
{
    /**
     * Get an integer less than, equal to, or greater than zero when $a is less
     * than, equal to, or greater than $b, respectively
     *
     * This method returns the equivalent of:
     *
     * ```php
     * $a <=> $b
     * ```
     *
     * @param static $a
     * @param static $b
     */
    public static function compare($a, $b): int;
}
