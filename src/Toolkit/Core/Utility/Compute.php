<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Lkrms\Support\Catalog\CharacterSequence as Char;
use Salient\Core\AbstractUtility;
use Stringable;

/**
 * Generate values like hashes and secure UUIDs
 */
final class Compute extends AbstractUtility
{
    /**
     * Get a cryptographically secure [RFC4122]-compliant UUID in raw binary
     * form
     */
    public static function binaryUuid(): string
    {
        return self::doUuid(true);
    }

    /**
     * Get a cryptographically secure [RFC4122]-compliant UUID in hexadecimal
     * form
     */
    public static function uuid(): string
    {
        return self::doUuid(false);
    }

    private static function doUuid(bool $binary): string
    {
        $uuid = [
            random_bytes(4),
            random_bytes(2),
            // Version 4 (most significant 4 bits = 0b0100)
            chr(random_int(0, 0x0F) | 0x40) . random_bytes(1),
            // Variant 1 (most significant 2 bits = 0b10)
            chr(random_int(0, 0x3F) | 0x80) . random_bytes(1),
            random_bytes(6),
        ];

        if ($binary) {
            return implode('', $uuid);
        }

        foreach ($uuid as $bin) {
            $hex[] = bin2hex($bin);
        }

        return implode('-', $hex);
    }

    /**
     * Get a cryptographically secure string
     */
    public static function randomText(int $length, string $chars = Char::ALPHANUMERIC): string
    {
        $max = strlen($chars) - 1;
        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= $chars[random_int(0, $max)];
        }
        return $text;
    }

    /**
     * Get a unique non-crypto hash in raw binary form
     *
     * @param int|float|string|bool|Stringable|null ...$value
     */
    public static function binaryHash(...$value): string
    {
        // xxHash isn't supported until PHP 8.1, so MD5 is the best fit
        return hash('md5', implode("\0", $value), true);
    }

    /**
     * Get a unique non-crypto hash in hexadecimal form
     *
     * @param int|float|string|bool|Stringable|null ...$value
     */
    public static function hash(...$value): string
    {
        return hash('md5', implode("\0", $value));
    }

    /**
     * Get the Levenshtein distance between two strings relative to the length
     * of the longest string
     *
     * @param bool $normalise If true, normalise `$string1` and `$string2` with
     * {@see Str::normalise()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * are identical, and `1` means they have no similarities.
     */
    public static function textDistance(
        string $string1,
        string $string2,
        bool $normalise = true
    ): float {
        if ($string1 === '' && $string2 === '') {
            return (float) 0;
        }

        if ($normalise) {
            $string1 = Str::normalise($string1);
            $string2 = Str::normalise($string2);
        }

        return
            levenshtein($string1, $string2)
            / max(strlen($string1), strlen($string2));
    }

    /**
     * Get the similarity of two strings relative to the length of the longest
     * string
     *
     * @param bool $normalise If true, normalise `$string1` and `$string2` with
     * {@see Str::normalise()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * have no similarities, and `1` means they are identical.
     */
    public static function textSimilarity(
        string $string1,
        string $string2,
        bool $normalise = true
    ): float {
        if ($string1 === '' && $string2 === '') {
            return (float) 1;
        }

        if ($normalise) {
            $string1 = Str::normalise($string1);
            $string2 = Str::normalise($string2);
        }

        return
            max(
                similar_text($string1, $string2),
                similar_text($string2, $string1),
            ) / max(
                strlen($string1),
                strlen($string2),
            );
    }

    /**
     * Get the ngrams shared between two strings relative to the number of
     * ngrams in the longest string
     *
     * @param bool $normalise If true, normalise `$string1` and `$string2` with
     * {@see Str::normalise()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * have no shared ngrams, and `1` means their ngrams are identical.
     */
    public static function ngramSimilarity(
        string $string1,
        string $string2,
        bool $normalise = true,
        int $size = 2
    ): float {
        return self::ngramScore(true, $string1, $string2, $normalise, $size);
    }

    /**
     * Get the ngrams shared between two strings relative to the number of
     * ngrams in the shortest string
     *
     * @param bool $normalise If true, normalise `$string1` and `$string2` with
     * {@see Str::normalise()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * have no shared ngrams, and `1` means their ngrams are identical.
     */
    public static function ngramIntersection(
        string $string1,
        string $string2,
        bool $normalise = true,
        int $size = 2
    ): float {
        return self::ngramScore(false, $string1, $string2, $normalise, $size);
    }

    private static function ngramScore(
        bool $relativeToLongest,
        string $string1,
        string $string2,
        bool $normalise,
        int $size
    ): float {
        if (strlen($string1) < $size && strlen($string2) < $size) {
            return (float) 1;
        }

        if ($normalise) {
            $string1 = Str::normalise($string1);
            $string2 = Str::normalise($string2);
        }

        $ngrams1 = self::ngrams($string1, $size);
        $ngrams2 = self::ngrams($string2, $size);
        $count =
            $relativeToLongest
                ? max(count($ngrams1), count($ngrams2))
                : min(count($ngrams1), count($ngrams2));

        $same = 0;
        foreach ($ngrams1 as $ngram) {
            $key = array_search($ngram, $ngrams2, true);
            if ($key !== false) {
                $same++;
                unset($ngrams2[$key]);
            }
        }

        return $same / $count;
    }

    /**
     * Get a string's n-grams
     *
     * @return string[]
     */
    public static function ngrams(string $string, int $size = 2): array
    {
        if (strlen($string) < $size) {
            return [];
        }

        $ngrams = [];
        for ($i = 0; $i < $size; $i++) {
            $split = $i
                ? substr($string, $i)
                : $string;
            $trim = strlen($split) % $size;
            if ($trim) {
                $split = substr($split, 0, -$trim);
            }
            if ($split === '') {
                continue;
            }
            $ngrams = array_merge($ngrams, str_split($split, $size));
        }

        return $ngrams;
    }
}
