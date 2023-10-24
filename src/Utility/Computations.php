<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Support\Catalog\CharacterSequence as Char;
use Lkrms\Utility\Convert;

/**
 * Generate values like hashes and secure UUIDs
 */
final class Computations
{
    /**
     * Generate a cryptographically secure random UUID
     *
     * Of the 128 bits returned, 122 are random.
     *
     * Compliant with [RFC4122].
     *
     * @param bool $binary If `true`, 16 bytes of raw binary data are returned
     * instead of a 36-byte hexadecimal representation.
     */
    public function uuid(bool $binary = false): string
    {
        $uuid[] = random_bytes(4);
        $uuid[] = random_bytes(2);
        // Version 4 (most significant 4 bits = 0b0100)
        $uuid[] = chr(ord(random_bytes(1)) & 0xf | 0x40) . random_bytes(1);
        // Variant 1 (most significant 2 bits = 0b10)
        $uuid[] = chr(ord(random_bytes(1)) & 0x3f | 0x80) . random_bytes(1);
        $uuid[] = random_bytes(6);

        if ($binary) {
            return implode('', $uuid);
        }

        return implode('-', array_map(fn(string $bin): string => bin2hex($bin), $uuid));
    }

    /**
     * Generate a cryptographically secure string
     */
    public function randomText(int $length, string $chars = Char::ALPHANUMERIC): string
    {
        $max = strlen($chars) - 1;
        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= $chars[random_int(0, $max)];
        }
        return $text;
    }

    /**
     * Generate a unique non-crypto hash and return raw binary data
     *
     * @param int|float|string|bool|\Stringable|null ...$value
     */
    public function binaryHash(...$value): string
    {
        // xxHash isn't supported until PHP 8.1, so MD5 is the best fit
        return hash('md5', implode("\0", Convert::toStrings(...$value)), true);
    }

    /**
     * Generate a unique non-crypto hash and return a hexadecimal string
     *
     * @param int|float|string|bool|\Stringable|null ...$value
     */
    public function hash(...$value): string
    {
        return hash('md5', implode("\0", Convert::toStrings(...$value)));
    }

    /**
     * Returns the Levenshtein distance between two strings relative to the
     * length of the longest string
     *
     * @param string $string1
     * @param string $string2
     * @param bool $normalise If true, normalise the strings with
     * {@see Conversions::toNormal()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * are identical, and `1` means they have no similarities.
     */
    public function textDistance(string $string1, string $string2, bool $normalise = true): float
    {
        if ($string1 . $string2 == '') {
            return (float) 0;
        }

        if ($normalise) {
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
     * {@see Conversions::toNormal()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * have no similarities, and `1` means they are identical.
     */
    public function textSimilarity(string $string1, string $string2, bool $normalise = true): float
    {
        if ($string1 . $string2 == '') {
            return (float) 1;
        }

        if ($normalise) {
            $string1 = Convert::toNormal($string1);
            $string2 = Convert::toNormal($string2);
        }

        return max(similar_text($string1, $string2), similar_text($string2, $string1)) / max(strlen($string1), strlen($string2));
    }
}
