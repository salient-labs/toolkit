<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Support\Catalog\CharacterSequence as Char;
use Lkrms\Support\Catalog\RegularExpression as Regex;

/**
 * Manipulate strings
 */
final class Str
{
    /**
     * Get the first string that is not null or empty, or return the last value
     */
    public static function coalesce(?string $string, ?string ...$strings): ?string
    {
        array_unshift($strings, $string);
        $last = array_pop($strings);
        foreach ($strings as $string) {
            if ($string === null || $string === '') {
                continue;
            }
            return $string;
        }
        return $last;
    }

    /**
     * Convert ASCII alphabetic characters in a string to lowercase
     */
    public static function lower(string $string): string
    {
        return strtr($string, Char::ALPHABETIC_UPPER, Char::ALPHABETIC_LOWER);
    }

    /**
     * Convert ASCII alphabetic characters in a string to uppercase
     */
    public static function upper(string $string): string
    {
        return strtr($string, Char::ALPHABETIC_LOWER, Char::ALPHABETIC_UPPER);
    }

    /**
     * Apply an end-of-line sequence to a string
     */
    public static function setEol(
        string $string,
        string $eol = "\n"
    ): string {
        switch ($eol) {
            case "\n":
                return str_replace(["\r\n", "\r"], $eol, $string);

            case "\r":
                return str_replace(["\r\n", "\n"], $eol, $string);

            case "\r\n":
                return str_replace(["\r\n", "\r", "\n"], ["\n", "\n", $eol], $string);

            default:
                throw new InvalidArgumentException(sprintf('Invalid end-of-line sequence: %s', $eol));
        }
    }

    /**
     * Split a string by a string, remove whitespace from the beginning and end
     * of each substring, remove empty strings
     *
     * @param string|null $characters Optionally specify characters to remove
     * instead of whitespace.
     * @return string[]
     */
    public static function splitAndTrim(string $separator, string $string, ?string $characters = null): array
    {
        return array_values(Arr::trim(
            explode($separator, $string),
            $characters
        ));
    }

    /**
     * Split a string by a string without separating substrings enclosed by
     * brackets, remove whitespace from the beginning and end of each substring,
     * remove empty strings
     *
     * @param string|null $characters Optionally specify characters to remove
     * instead of whitespace.
     * @return string[]
     */
    public static function splitAndTrimOutsideBrackets(string $separator, string $string, ?string $characters = null): array
    {
        return array_values(Arr::trim(
            self::splitOutsideBrackets($separator, $string),
            $characters
        ));
    }

    /**
     * Split a string by a string without separating substrings enclosed by
     * brackets
     *
     * @return string[]
     */
    public static function splitOutsideBrackets(string $separator, string $string): array
    {
        if (strlen($separator) !== 1) {
            throw new InvalidArgumentException('Separator must be a single character');
        }

        if (strpos('()<>[]{}', $separator) !== false) {
            throw new InvalidArgumentException('Separator cannot be a bracket character');
        }

        $quoted = preg_quote($separator, '/');

        $escaped = $separator;
        if (strpos('\-', $separator) !== false) {
            $escaped = '\\' . $separator;
        }

        $regex = <<<REGEX
            (?x)
            (?: [^()<>[\]{}{$escaped}]++ |
              ( \( (?: [^()<>[\]{}]*+ (?-1)? )*+ \) |
                <  (?: [^()<>[\]{}]*+ (?-1)? )*+ >  |
                \[ (?: [^()<>[\]{}]*+ (?-1)? )*+ \] |
                \{ (?: [^()<>[\]{}]*+ (?-1)? )*+ \} ) |
              # Match empty substrings
              (?<= $quoted ) (?= $quoted ) )+
            REGEX;

        Pcre::matchAll(
            Regex::delimit($regex),
            $string,
            $matches,
        );

        return $matches[0];
    }

    /**
     * Wrap a string to a given number of characters, optionally varying the
     * widths of the second and subsequent lines from the first
     *
     * If `$width` is an `array`, the first line of text is wrapped to the first
     * value, and text in subsequent lines is wrapped to the second value.
     *
     * @param array{int,int}|int $width
     */
    public static function wordwrap(
        string $string,
        $width = 75,
        string $break = "\n",
        bool $cutLongWords = false
    ): string {
        [$delta, $width] = is_array($width)
            ? [$width[1] - $width[0], $width[1]]
            : [0, $width];

        if (!$delta) {
            return wordwrap($string, $width, $break, $cutLongWords);
        }

        // For hanging indents, remove and restore the first $delta characters
        if ($delta < 0) {
            return substr($string, 0, -$delta)
                . wordwrap(substr($string, -$delta), $width, $break, $cutLongWords);
        }

        // For first line indents, add and remove $delta characters
        return substr(
            wordwrap(str_repeat('x', $delta) . $string, $width, $break, $cutLongWords),
            $delta
        );
    }
}
