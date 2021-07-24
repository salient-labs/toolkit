<?php

declare(strict_types=1);

namespace Lkrms;

/**
 * Functions for value testing
 *
 * @package Lkrms
 */
class Test
{
    /**
     * Check if a flag is set in a bitmask
     *
     * If `$mask` is not set, returns `true` if bits set in `$flag` are also set
     * in `$value`.
     *
     * If `$mask` is set, returns `true` if masked bits in `$flag` and `$value`
     * have the same state.
     *
     * @param int $value The bitmask being checked.
     * @param int $flag The value of the flag.
     * @param null|int $mask The mask being applied to `$value` and `$flag`.
     * @return bool
     */
    public static function IsFlagSet(int $value, int $flag, ?int $mask = null): bool
    {
        return ($value & ($mask ?? $flag)) === $flag;
    }

    /**
     * Check if only one flag is set in a bitmask
     *
     * Returns `true` if exactly one of the masked bits in `$value` is set.
     *
     * @param int $value The bitmask being checked.
     * @param int $mask The mask being applied to `$value`.
     * @return bool
     */
    public static function IsOneFlagSet(int $value, int $mask): bool
    {
        return substr_count(decbin($value & $mask), "1") === 1;
    }
}

