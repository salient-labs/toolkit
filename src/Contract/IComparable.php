<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Compares itself with others of the same type
 *
 */
interface IComparable
{
    /**
     * Return the equivalent of $this <=> $b
     *
     * @param static $b
     */
    public function compare($b, bool $strict = false): int;
}
