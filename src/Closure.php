<?php

declare(strict_types=1);

namespace Lkrms;

use UnexpectedValueException;

/**
 * A generator generator
 *
 * @package Lkrms
 */
abstract class Closure
{
    /**
     * Add missing and unmapped values to the output
     */
    public const SKIP_NONE = 0;

    /**
     * If input values are missing, don't add them to the output
     */
    public const SKIP_MISSING = 1;

    /**
     * If input values are null, don't add them to the output
     */
    public const SKIP_NULL = 2;

    /**
     * If input values haven't been mapped to output values, discard them.
     */
    public const SKIP_UNMAPPED = 4;

    private static $ArrayMappers = [];

    /**
     * Returns a closure that moves array values from one set of keys to another
     *
     * Closure signature:
     *
     * ```php
     * static function (array $in): array
     * ```
     *
     * @param array<int|string,int|string> $keyMap An array that maps input keys
     * to output keys.
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * input array has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `Closure::SKIP_*` values.
     * @return \Closure
     */
    public static function getArrayMapper(
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = Closure::SKIP_MISSING | Closure::SKIP_UNMAPPED
    ): \Closure
    {
        $sig = implode("\000", array_merge(
            array_keys($keyMap),
            array_values($keyMap),
            [$sameKeys, $skip]
        ));

        if ($closure = self::$ArrayMappers[$sig] ?? null)
        {
            return $closure;
        }

        if ($sameKeys)
        {
            $outKeys = array_values($keyMap);
            $closure = static function (array $in) use ($outKeys): array
            {
                $out = array_combine($outKeys, $in);

                if ($out === false)
                {
                    throw new UnexpectedValueException("Invalid input array");
                }

                return $out;
            };
        }
        else
        {
            $closure = static function (array $in) use ($keyMap): array
            {
                $out = [];

                foreach ($in as $key => $value)
                {
                    $out[$keyMap[$key] ?? $key] = $value;
                }

                return $out;
            };

            if ($skip & self::SKIP_UNMAPPED)
            {
                $closure = static function (array $in) use ($keyMap, $closure): array
                {
                    // Only keep mapped values
                    $in = array_intersect_key($in, $keyMap);

                    return $closure($in);
                };
            }
            else
            {
                $flipped = array_flip($keyMap);
                $closure = static function (array $in) use ($keyMap, $flipped, $closure): array
                {
                    // In addition to mapped values, keep values that aren't
                    // mapped (`array_diff_key($in, $keyMap...`) and don't have
                    // the same key as a mapped output key (`...$flipped`)
                    $in = array_intersect_key($in, $keyMap + array_diff_key($in, $keyMap, $flipped));

                    return $closure($in);
                };
            }

            if (!($skip & self::SKIP_MISSING))
            {
                $closure = static function (array $in) use ($keyMap, $closure): array
                {
                    $missing = array_diff_key($keyMap, $in);

                    return $closure($in) + array_combine(
                        array_values($missing),
                        array_fill(0, count($missing), null)
                    );
                };
            }
        }

        if ($skip & self::SKIP_NULL)
        {
            $closure = static function (array $in) use ($closure): array
            {
                return array_filter(
                    $closure($in),
                    function ($value) { return !is_null($value); }
                );
            };
        }

        self::$ArrayMappers[$sig] = $closure;

        return $closure;
    }
}
