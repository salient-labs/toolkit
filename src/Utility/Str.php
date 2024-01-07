<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Support\Catalog\CharacterSequence as Char;
use Lkrms\Support\Catalog\RegularExpression as Regex;

/**
 * Manipulate strings
 */
final class Str extends Utility
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
     * If the first character in a string is an ASCII alphabetic character, make
     * it uppercase
     */
    public static function upperFirst(string $string): string
    {
        if ($string === '') {
            return $string;
        }
        $string[0] = self::upper($string[0]);
        return $string;
    }

    /**
     * Apply an end-of-line sequence to a string
     */
    public static function setEol(string $string, string $eol = "\n"): string
    {
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
     * Convert words in an arbitrarily capitalised string to snake_case,
     * optionally preserving given characters
     *
     * @see Str::toWords()
     */
    public static function toSnakeCase(string $string, ?string $preserve = null): string
    {
        return self::lower(self::toWords($string, '_', $preserve));
    }

    /**
     * Convert words in an arbitrarily capitalised string to kebab-case,
     * optionally preserving given characters
     *
     * @see Str::toWords()
     */
    public static function toKebabCase(string $string, ?string $preserve = null): string
    {
        return self::lower(self::toWords($string, '-', $preserve));
    }

    /**
     * Convert words in an arbitrarily capitalised string to camelCase,
     * optionally preserving given characters
     *
     * @see Str::toWords()
     */
    public static function toCamelCase(string $string, ?string $preserve = null): string
    {
        return Pcre::replaceCallback(
            '/(?<![[:alnum:]])[[:alpha:]]/u',
            fn($matches) => self::lower($matches[0]),
            self::toPascalCase($string, $preserve)
        );
    }

    /**
     * Convert words in an arbitrarily capitalised string to PascalCase,
     * optionally preserving given characters
     *
     * @see Str::toWords()
     */
    public static function toPascalCase(string $string, ?string $preserve = null): string
    {
        return self::toWords(
            $string,
            '',
            $preserve,
            fn($word) => self::upperFirst(self::lower($word))
        );
    }

    /**
     * Get the words in an arbitrarily capitalised string and delimit them with
     * a given separator, optionally preserving given characters and applying a
     * callback to each word
     *
     * Words in `$string` may be separated by any combination of
     * non-alphanumeric characters and capitalisation. For example:
     *
     * - `foo bar` => foo bar
     * - `FOO_BAR` => FOO BAR
     * - `fooBar` => foo Bar
     * - `$this = fooBar` => this foo Bar
     * - `PHPDoc` => PHP Doc
     *
     * This method forms the basis of capitalisation methods.
     *
     * @param string|null $preserve Characters to keep in the string.
     * Alphanumeric characters are always preserved.
     * @param (callable(string): string)|null $callback
     */
    public static function toWords(
        string $string,
        string $separator = ' ',
        ?string $preserve = null,
        ?callable $callback = null
    ): string {
        $notAfterPreserve = '';
        if ((string) $preserve !== '') {
            $preserve = Pcre::replace('/[[:alnum:]]/u', '', (string) $preserve);
            if ($preserve !== '') {
                // Prevent "key=value" becoming "key= value" when preserving "="
                // by asserting that when separating words, they must appear:
                // - immediately after the previous word (\G)
                // - after an unpreserved character, or
                // - at a word boundary (e.g. "Value" in "key=someValue")
                $preserve = Pcre::quoteCharacterClass($preserve, '/');
                $notAfterPreserve = "(?:\G|(?<=[^[:alnum:]{$preserve}])|(?<=[[:lower:][:digit:]])(?=[[:upper:]]))";
            }
        }
        $preserve = "[:alnum:]{$preserve}";
        $word = '(?:[[:upper:]]?[[:lower:][:digit:]]+|(?:[[:upper:]](?![[:lower:]]))+[[:digit:]]*)';

        // Insert separators before words not adjacent to a preserved character
        // to prevent "foo bar" becoming "foobar", for example
        if ($separator !== '') {
            $string = Pcre::replace(
                "/$notAfterPreserve$word/u",
                $separator . '$0',
                $string
            );
        }

        if ($callback !== null) {
            $string = Pcre::replaceCallback(
                "/$word/u",
                fn(array $match): string => $callback($match[0]),
                $string
            );
        }

        // Trim unpreserved characters from the beginning and end of the string,
        // then replace sequences of one or more unpreserved characters with one
        // separator
        $string = Pcre::replace([
            "/^[^{$preserve}]++|[^{$preserve}]++\$/u",
            "/[^{$preserve}]++/u",
        ], [
            '',
            $separator,
        ], $string);

        return $string;
    }

    /**
     * Copy a string to a temporary stream
     *
     * @return resource
     */
    public static function toStream(string $string)
    {
        $stream = File::open('php://temp', 'r+');
        File::write($stream, $string);
        File::seek($stream, 0);
        return $stream;
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

    /**
     * Enclose a string between delimiters
     *
     * @param string|null $after If `null`, `$before` is used before and after
     * the string.
     */
    public static function wrap(string $string, string $before, ?string $after = null): string
    {
        return $before . $string . ($after ?? $before);
    }
}
