<?php

declare(strict_types=1);

namespace Lkrms;

use Closure;
use ReflectionFunction;

/**
 * Literally so random
 *
 * @package Lkrms
 */
class Generate
{
    /**
     * Generate a cryptographically secure random UUID
     *
     * Compliant with RFC4122.
     *
     * @return string
     */
    public static function uuid(): string
    {
        $bytes  = random_bytes(16);
        $uuid   = [];
        $uuid[] = bin2hex(substr($bytes, 0, 4));
        $uuid[] = bin2hex(substr($bytes, 4, 2));
        $uuid[] = bin2hex(chr(ord(substr($bytes, 6, 1)) & 0xf | 0x40) . substr($bytes, 7, 1));
        $uuid[] = bin2hex(chr(ord(substr($bytes, 8, 1)) & 0x3f | 0x80) . substr($bytes, 9, 1));
        $uuid[] = bin2hex(substr($bytes, 10, 6));

        return implode("-", $uuid);
    }

    /**
     * Generate a unique non-crypto hash
     *
     * @param array<int|string,string|Stringable> $value One or more values to
     * hash.
     * @return string
     */
    public static function hash(...$value): string
    {
        // xxHash isn't supported until PHP 8.1, so MD5 is the best fit
        return hash("md5", implode("\000", Convert::toStrings(...$value)));
    }

    /**
     * Generate a hash that uniquely identifies a Closure (or any other
     * callable)
     *
     * @param callable $closure
     * @return string
     */
    public static function closureHash(callable $closure): string
    {
        if (!($closure instanceof Closure))
        {
            $closure = Closure::fromCallable($closure);
        }

        $closure = new ReflectionFunction($closure);

        // ReflectionFunction::__toString() is unambiguous and consistent
        return self::hash((string)$closure);
    }

    /**
     * Returns the Levenshtein distance between two strings relative to the
     * length of the longest string
     *
     * @param string $string1
     * @param string $string2
     * @param bool $normalise If true, normalise the strings with
     * {@see Convert::toNormal()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * are identical, and `1` means they have no similarities.
     */
    public static function textDistance(
        string $string1,
        string $string2,
        bool $normalise = true
    ): float
    {
        if ($string1 . $string2 == "")
        {
            return (float)0;
        }

        if ($normalise)
        {
            $string1 = Convert::toNormal($string1);
            $string2 = Convert::toNormal($string2);
        }

        return levenshtein($string1, $string2) / max(strlen($string1), strlen($string2));
    }

    /**
     * Returns the similarity of two strings relative to the length of the
     * longest string
     *
     * @param string $string1
     * @param string $string2
     * @param bool $normalise If true, normalise the strings with
     * {@see Convert::toNormal()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * have no similarities, and `1` means they are identical.
     */
    public static function textSimilarity(
        string $string1,
        string $string2,
        bool $normalise = true
    ): float
    {
        if ($string1 . $string2 == "")
        {
            return (float)1;
        }

        if ($normalise)
        {
            $string1 = Convert::toNormal($string1);
            $string2 = Convert::toNormal($string2);
        }

        return max(similar_text($string1, $string2), similar_text($string2, $string1)) / max(strlen($string1), strlen($string2));
    }
}

