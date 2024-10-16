<?php declare(strict_types=1);

namespace Salient\Utility;

use Closure;
use InvalidArgumentException;

/**
 * Work with strings
 *
 * @api
 */
final class Str extends AbstractUtility
{
    public const ALPHA = Str::LOWER . Str::UPPER;
    public const ALPHANUMERIC = Str::ALPHA . Str::NUMERIC;
    public const HEX = '0123456789abcdefABCDEF';
    public const LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const NUMERIC = '0123456789';
    public const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const PRESERVE_DOUBLE_QUOTED = 1;
    public const PRESERVE_SINGLE_QUOTED = 2;
    public const PRESERVE_QUOTED = Str::PRESERVE_DOUBLE_QUOTED | Str::PRESERVE_SINGLE_QUOTED;
    public const ASCII_EXTENDED = "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";

    /**
     * Default value of mergeLists() parameter $itemRegex
     */
    public const DEFAULT_ITEM_REGEX = '/^(?<indent>\h*[-*] )/';

    /**
     * Get the first string that is not null or empty, or return the last value
     */
    public static function coalesce(?string ...$strings): ?string
    {
        $string = null;
        foreach ($strings as $string) {
            if ($string === null || $string === '') {
                continue;
            }
            return $string;
        }
        return $string;
    }

    /**
     * Convert an ASCII string to lowercase
     */
    public static function lower(string $string): string
    {
        return strtr($string, self::UPPER, self::LOWER);
    }

    /**
     * Convert an ASCII string to uppercase
     */
    public static function upper(string $string): string
    {
        return strtr($string, self::LOWER, self::UPPER);
    }

    /**
     * Make the first character in an ASCII string uppercase
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
     * Match an ASCII string's case to another string
     */
    public static function matchCase(string $string, string $match): string
    {
        $match = trim($match);

        if ($match === '') {
            return $string;
        }

        $upper = strpbrk($match, self::UPPER);
        $hasUpper = $upper !== false;
        $hasLower = strpbrk($match, self::LOWER) !== false;

        if ($hasUpper && !$hasLower && strlen($match) > 1) {
            return self::upper($string);
        }

        if (!$hasUpper && $hasLower) {
            return self::lower($string);
        }

        if (
            // @phpstan-ignore booleanNot.alwaysTrue
            (!$hasUpper && !$hasLower)
            || $upper !== $match
        ) {
            return $string;
        }

        return self::upperFirst(self::lower($string));
    }

    /**
     * Check if a string starts with a given substring
     *
     * @param iterable<string>|string $needles
     */
    public static function startsWith(string $haystack, $needles, bool $ignoreCase = false): bool
    {
        if (!is_iterable($needles)) {
            $needles = [$needles];
        }
        if ($ignoreCase) {
            $haystack = self::lower($haystack);
            $needles = Arr::lower($needles);
        }
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($haystack, $needle) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a string ends with a given substring
     *
     * @param iterable<string>|string $needles
     */
    public static function endsWith(string $haystack, $needles, bool $ignoreCase = false): bool
    {
        if (!is_iterable($needles)) {
            $needles = [$needles];
        }
        if ($ignoreCase) {
            $haystack = self::lower($haystack);
            $needles = Arr::lower($needles);
        }
        foreach ($needles as $needle) {
            if ($needle !== '' && substr($haystack, -strlen($needle)) === $needle) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if every character in a string has a codepoint between 0 and 127
     */
    public static function isAscii(string $string): bool
    {
        return strcspn($string, self::ASCII_EXTENDED) === strlen($string);
    }

    /**
     * Normalise a string for comparison
     *
     * The return value of this method is not covered by the Salient toolkit's
     * backward compatibility promise.
     */
    public static function normalise(string $string): string
    {
        // 4. Remove leading and trailing whitespace
        // 5. Convert ASCII characters to uppercase
        return self::upper(trim(Regex::replace([
            // 1. Replace "&" with " and "
            '/([[:alnum:]][^&]*+)&(?=[^&[:alnum:]]*+[[:alnum:]])/u',
            // 2. Remove "."
            '/\.++/',
            // 3. Replace non-alphanumeric character sequences with " "
            '/[^[:alnum:]]++/u',
        ], [
            '$1 and ',
            '',
            ' ',
        ], $string)));
    }

    /**
     * Replace the end of a string with an ellipsis ("...") if its length
     * exceeds a limit
     */
    public static function ellipsize(string $value, int $length): string
    {
        if ($length < 3) {
            $length = 3;
        }
        if (mb_strlen($value) > $length) {
            return rtrim(mb_substr($value, 0, $length - 3)) . '...';
        }

        return $value;
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
                return str_replace("\n", $eol, self::setEol($string));
        }
    }

    /**
     * Remove native end-of-line sequences from the end of a string
     */
    public static function trimNativeEol(string $string): string
    {
        if (\PHP_EOL === "\n") {
            $s = rtrim($string, "\n");
            if ($s === $string || $s === '' || $s[-1] !== "\r") {
                return $s;
            }
            return "$s\n";
        }

        $length = strlen(\PHP_EOL);
        while (substr($string, -$length) === \PHP_EOL) {
            $string = substr($string, 0, -$length);
        }

        return $string;
    }

    /**
     * Replace newline characters in a string with the native end-of-line
     * sequence
     */
    public static function eolToNative(string $string): string
    {
        return \PHP_EOL === "\n"
            ? $string
            : str_replace("\n", \PHP_EOL, $string);
    }

    /**
     * Replace native end-of-line sequences in a string with the newline
     * character
     */
    public static function eolFromNative(string $string): string
    {
        return \PHP_EOL === "\n"
            ? $string
            : str_replace(\PHP_EOL, "\n", $string);
    }

    /**
     * Convert words in an arbitrarily capitalised string to snake_case,
     * optionally preserving non-word characters
     */
    public static function snake(string $string, string $preserve = ''): string
    {
        return self::lower(self::words($string, '_', $preserve));
    }

    /**
     * Convert words in an arbitrarily capitalised string to kebab-case,
     * optionally preserving non-word characters
     */
    public static function kebab(string $string, string $preserve = ''): string
    {
        return self::lower(self::words($string, '-', $preserve));
    }

    /**
     * Convert words in an arbitrarily capitalised string to camelCase,
     * optionally preserving non-word characters
     */
    public static function camel(string $string, string $preserve = ''): string
    {
        return Regex::replaceCallback(
            '/(?<![[:alnum:]])[[:alpha:]]/u',
            fn($matches) => self::lower($matches[0]),
            self::pascal($string, $preserve),
        );
    }

    /**
     * Convert words in an arbitrarily capitalised string to PascalCase,
     * optionally preserving non-word characters
     */
    public static function pascal(string $string, string $preserve = ''): string
    {
        return self::words($string, '', $preserve, fn($string) => self::upperFirst(self::lower($string)));
    }

    /**
     * Get words from an arbitrarily capitalised string and delimit them with a
     * separator, optionally preserving non-word characters and applying a
     * callback to each word
     *
     * A word consists of one or more letters of the same case, or one uppercase
     * letter followed by zero or more lowercase letters. Numbers are treated as
     * lowercase letters except that two or more uppercase letters form one word
     * with any subsequent numbers.
     *
     * @param string $preserve Non-alphanumeric characters to preserve.
     * @param (Closure(string): string)|null $callback
     */
    public static function words(
        string $string,
        string $separator = ' ',
        string $preserve = '',
        ?Closure $callback = null
    ): string {
        $notAfterPreserve = '';
        if ($preserve !== '') {
            $preserve = Regex::replace('/[[:alnum:]]++/u', '', $preserve);
            if ($preserve !== '') {
                $preserve = Regex::quoteCharacterClass($preserve, '/');
                // Prevent "key=value" becoming "key= value" when preserving "="
                // by asserting that when separating words, they must appear:
                // - immediately after the previous word (\G),
                // - after an unpreserved character, or
                // - at a word boundary (e.g. "Value" in "key=someValue")
                if ($separator !== '') {
                    $notAfterPreserve = '(?:\G'
                        . "|(?<=[^[:alnum:]{$preserve}])"
                        . '|(?<=[[:lower:][:digit:]])(?=[[:upper:]]))';
                }
            }
        }
        $preserve = "[:alnum:]{$preserve}";
        $word = '(?:[[:upper:]]?[[:lower:][:digit:]]++'
            . '|(?:[[:upper:]](?![[:lower:]]))++[[:digit:]]*+)';

        // Insert separators before words to prevent "foo bar" becoming "foobar"
        if ($separator !== '') {
            if (Regex::match("/[{$preserve}]/u", $separator)) {
                throw new InvalidArgumentException('Invalid separator (preserved characters cannot be used)');
            }
            $separator = Regex::quoteReplacement($separator);
            $string = Regex::replace(
                "/$notAfterPreserve$word/u",
                $separator . '$0',
                $string,
            );
        }

        if ($callback !== null) {
            $string = Regex::replaceCallback(
                "/$word/u",
                fn($matches) => $callback($matches[0]),
                $string,
            );
        }

        // Trim unpreserved characters from the beginning and end of the string,
        // then replace sequences of them with one separator
        return Regex::replace([
            "/^[^{$preserve}]++|[^{$preserve}]++\$/Du",
            "/[^{$preserve}]++/u",
        ], [
            '',
            $separator,
        ], $string);
    }

    /**
     * Expand tabs in a string to spaces
     *
     * @param int $column The starting column (1-based) of `$text`.
     */
    public static function expandTabs(
        string $string,
        int $tabSize = 8,
        int $column = 1
    ): string {
        if (strpos($string, "\t") === false) {
            return $string;
        }
        $eol = Get::eol($string) ?? "\n";
        $expanded = '';
        foreach (explode($eol, $string) as $i => $line) {
            !$i || $expanded .= $eol;
            $parts = explode("\t", $line);
            $last = array_key_last($parts);
            foreach ($parts as $p => $part) {
                $expanded .= $part;
                if ($p === $last) {
                    break;
                }
                $column += mb_strlen($part);
                // e.g. with $tabSize 4, a tab at $column 2 occupies 3 spaces
                $spaces = $tabSize - (($column - 1) % $tabSize);
                $expanded .= str_repeat(' ', $spaces);
                $column += $spaces;
            }
            $column = 1;
        }
        return $expanded;
    }

    /**
     * Expand leading tabs in a string to spaces
     *
     * @param bool $preserveLine1 If `true`, tabs in the first line of `$text`
     * are not expanded.
     * @param int $column The starting column (1-based) of `$text`.
     */
    public static function expandLeadingTabs(
        string $string,
        int $tabSize = 8,
        bool $preserveLine1 = false,
        int $column = 1
    ): string {
        if (strpos($string, "\t") === false) {
            return $string;
        }
        $eol = Get::eol($string) ?? "\n";
        $softTab = str_repeat(' ', $tabSize);
        $expanded = '';
        foreach (explode($eol, $string) as $i => $line) {
            !$i || $expanded .= $eol;
            if ($i || (!$preserveLine1 && $column === 1)) {
                $expanded .= Regex::replace('/(?<=\n|\G)\t/', $softTab, $line);
                continue;
            }
            if ($preserveLine1) {
                $expanded .= $line;
                continue;
            }
            $parts = explode("\t", $line);
            while (($part = array_shift($parts)) !== null) {
                $expanded .= $part;
                if (!$parts) {
                    break;
                }
                if ($part !== '') {
                    $expanded .= "\t" . implode("\t", $parts);
                    break;
                }
                $column += mb_strlen($part);
                $spaces = $tabSize - (($column - 1) % $tabSize);
                $expanded .= str_repeat(' ', $spaces);
                $column += $spaces;
            }
        }
        return $expanded;
    }

    /**
     * Copy a string to a temporary stream
     *
     * @return resource
     */
    public static function toStream(string $string)
    {
        $stream = File::open('php://temp', 'r+');
        File::writeAll($stream, $string);
        File::rewind($stream);
        return $stream;
    }

    /**
     * Split a string by a string, trim substrings and remove any empty strings
     *
     * @param non-empty-string $separator
     * @param int|null $limit The maximum number of substrings to return. If
     * given, empty strings are not removed.
     * @param string|null $characters Characters to trim, `null` (the default)
     * to trim whitespace, or an empty string to trim nothing.
     * @return ($removeEmpty is true ? list<string> : non-empty-list<string>)
     */
    public static function split(
        string $separator,
        string $string,
        ?int $limit = null,
        bool $removeEmpty = true,
        ?string $characters = null
    ): array {
        if ($limit !== null) {
            $removeEmpty = false;
        }
        $split = Arr::trim(
            explode($separator, $string, $limit ?? \PHP_INT_MAX),
            $characters,
            $removeEmpty
        );
        return $removeEmpty ? $split : array_values($split);
    }

    /**
     * Split a string by a string without splitting bracket-delimited or
     * double-quoted substrings, trim substrings and remove any empty strings
     *
     * @param non-empty-string $separator
     * @param string|null $characters Characters to trim, `null` (the default)
     * to trim whitespace, or an empty string to trim nothing.
     * @param int-mask-of<Str::PRESERVE_*> $flags
     * @return ($removeEmpty is true ? list<string> : non-empty-list<string>)
     */
    public static function splitDelimited(
        string $separator,
        string $string,
        bool $removeEmpty = true,
        ?string $characters = null,
        int $flags = Str::PRESERVE_DOUBLE_QUOTED
    ): array {
        if (strlen($separator) !== 1) {
            throw new InvalidArgumentException('Separator must be a single character');
        }

        $quotes = '';
        $regex = '';
        if ($flags & self::PRESERVE_DOUBLE_QUOTED) {
            $quotes .= '"';
            $regex .= ' | " (?: [^"\\\\] | \\\\ . )*+ "';
        }
        if ($flags & self::PRESERVE_SINGLE_QUOTED) {
            $quotes .= "'";
            $regex .= " | ' (?: [^'\\\\] | \\\\ . )*+ '";
        }

        if (strpos('()<>[]{}' . $quotes, $separator) !== false) {
            throw new InvalidArgumentException('Separator cannot be a delimiter');
        }

        $quoted = preg_quote($separator, '/');
        $escaped = Regex::quoteCharacterClass($separator, '/');

        $regex = <<<REGEX
(?x)
(?: [^{$quotes}()<>[\]{}{$escaped}]++ |
  ( \( (?: [^{$quotes}()<>[\]{}]*+ (?-1)? )*+ \) |
    <  (?: [^{$quotes}()<>[\]{}]*+ (?-1)? )*+ >  |
    \[ (?: [^{$quotes}()<>[\]{}]*+ (?-1)? )*+ \] |
    \{ (?: [^{$quotes}()<>[\]{}]*+ (?-1)? )*+ \}{$regex} ) |
  # Match empty substrings
  (?<= $quoted | ^ ) (?= $quoted | \$ ) )+
REGEX;

        Regex::matchAll(
            Regex::delimit($regex, '/'),
            $string,
            $matches,
        );

        $split = Arr::trim(
            $matches[0],
            $characters,
            $removeEmpty
        );

        return $removeEmpty ? $split : array_values($split);
    }

    /**
     * Wrap a string to a given number of characters, optionally varying the
     * width of the first line
     *
     * @param int|array{int,int} $width The number of characters at which the
     * string will be wrapped, or `[ <first_line_width>, <width> ]`.
     */
    public static function wrap(
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
            $delta,
        );
    }

    /**
     * Undo wordwrap(), preserving Markdown-style paragraphs and lists
     *
     * Non-consecutive line breaks are converted to spaces except before:
     *
     * - four or more spaces
     * - one or more tabs
     * - Markdown-style list items (e.g. `- item`, `1. item`)
     *
     * @param bool $ignoreEscapes If `false`, preserve escaped whitespace.
     * @param bool $trimLines If `true`, remove whitespace from the end of each
     * line and between unwrapped lines.
     * @param bool $collapseBlankLines If `true`, collapse three or more
     * subsequent line breaks to two.
     */
    public static function unwrap(
        string $string,
        string $break = "\n",
        bool $ignoreEscapes = true,
        bool $trimLines = false,
        bool $collapseBlankLines = false
    ): string {
        $newline = preg_quote($break, '/');
        $noEscape = $ignoreEscapes ? '' : '(?<!\\\\)(?:\\\\\\\\)*\K';

        if ($trimLines) {
            $search[] = "/{$noEscape}\h+({$newline})/";
            $replace[] = '$1';
            $between = '\h*';
        } else {
            $between = '';
        }

        $search[] = "/{$noEscape}(?<!{$newline}|^){$newline}(?!{$newline}|\$|    |\\t|(?:[-+*]|[0-9]+[).])\h){$between}/D";
        $replace[] = ' ';

        if ($collapseBlankLines) {
            $search[] = "/(?:{$newline}){3,}/";
            $replace[] = $break . $break;
        }

        return Regex::replace($search, $replace, $string);
    }

    /**
     * Replace whitespace character sequences in a string with a single space
     */
    public static function collapse(string $string): string
    {
        return Regex::replace('/\s++/', ' ', $string);
    }

    /**
     * Enclose a string between delimiters
     *
     * @param string|null $after If `null`, `$before` is used before and after
     * the string.
     */
    public static function enclose(string $string, string $before, ?string $after = null): string
    {
        return $before . $string . ($after ?? $before);
    }

    /**
     * Get the Levenshtein distance between two strings relative to the length
     * of the longest string
     *
     * @param bool $normalise If `true`, call {@see Str::normalise()} to
     * normalise `$string1` and `$string2` for comparison.
     * @return float A value between `0` and `1`, where `0` means the strings
     * are identical, and `1` means they have no similarities.
     */
    public static function distance(
        string $string1,
        string $string2,
        bool $normalise = false
    ): float {
        if ($normalise) {
            $string1 = self::normalise($string1);
            $string2 = self::normalise($string2);
        }

        if ($string1 === '' && $string2 === '') {
            return 0.0;
        }

        return levenshtein($string1, $string2)
            / max(strlen($string1), strlen($string2));
    }

    /**
     * Get the similarity of two strings relative to the length of the longest
     * string
     *
     * @param bool $normalise If `true`, call {@see Str::normalise()} to
     * normalise `$string1` and `$string2` for comparison.
     * @return float A value between `0` and `1`, where `0` means the strings
     * have no similarities, and `1` means they are identical.
     */
    public static function similarity(
        string $string1,
        string $string2,
        bool $normalise = false
    ): float {
        if ($normalise) {
            $string1 = self::normalise($string1);
            $string2 = self::normalise($string2);
        }

        if ($string1 === '' && $string2 === '') {
            return 1.0;
        }

        return max(
            similar_text($string1, $string2),
            similar_text($string2, $string1),
        ) / max(
            strlen($string1),
            strlen($string2),
        );
    }

    /**
     * Get ngrams shared between two strings relative to the number of
     * ngrams in the longest string
     *
     * @param bool $normalise If `true`, call {@see Str::normalise()} to
     * normalise `$string1` and `$string2` for comparison.
     * @return float A value between `0` and `1`, where `0` means the strings
     * have no shared ngrams, and `1` means their ngrams are identical.
     */
    public static function ngramSimilarity(
        string $string1,
        string $string2,
        bool $normalise = false,
        int $size = 2
    ): float {
        return self::ngramScore(true, $string1, $string2, $normalise, $size);
    }

    /**
     * Get ngrams shared between two strings relative to the number of
     * ngrams in the shortest string
     *
     * @param bool $normalise If `true`, call {@see Str::normalise()} to
     * normalise `$string1` and `$string2` for comparison.
     * @return float A value between `0` and `1`, where `0` means the strings
     * have no shared ngrams, and `1` means their ngrams are identical.
     */
    public static function ngramIntersection(
        string $string1,
        string $string2,
        bool $normalise = false,
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
        if ($normalise) {
            $string1 = self::normalise($string1);
            $string2 = self::normalise($string2);
        }

        if (strlen($string1) < $size && strlen($string2) < $size) {
            return 1.0;
        }

        $ngrams1 = self::ngrams($string1, $size);
        $ngrams2 = self::ngrams($string2, $size);
        $count = $relativeToLongest
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

    /**
     * Group lists in a string by heading and remove duplicate items
     *
     * - Lines in `$text` are processed in order, from first to last
     * - If a non-empty line matches `$itemRegex`, it is treated as a list item,
     *   otherwise it becomes the current heading
     * - The current heading is cleared when an empty line is encountered after
     *   a list item (unless `$loose` is `true`)
     * - Top-level lines (i.e. headings with no items, and items with no
     *   heading) are returned before lists with headings
     * - If `$itemRegex` has a named subpattern called `indent` that matches a
     *   non-empty string, subsequent lines with indentation of the same width
     *   are treated as a continuation of the item, along with any empty lines
     *   between them
     *
     * @param string $listSeparator Inserted between headings and lists.
     * @param string|null $headingPrefix Inserted before headings, e.g. `"-"`.
     * Indentation of the same width is applied to subsequent list items.
     * @param bool $clean If `true`, remove the first match of `$itemRegex` from
     * the beginning of each item with no heading.
     * @param bool $loose If `true`, do not clear the current heading when an
     * empty line is encountered.
     * @param bool $discardEmpty If `true`, discard headings with no items.
     */
    public static function mergeLists(
        string $string,
        string $listSeparator = "\n",
        ?string $headingPrefix = null,
        ?string $itemRegex = Str::DEFAULT_ITEM_REGEX,
        bool $clean = false,
        bool $loose = false,
        bool $discardEmpty = false,
        string $eol = "\n",
        int $tabSize = 4
    ): string {
        $prefix = self::coalesce($headingPrefix, null);
        $regex = $itemRegex ?? self::DEFAULT_ITEM_REGEX;

        if ($prefix !== null) {
            $prefixIsItem = (bool) Regex::match($regex, $prefix);
            $prefixBytes = strlen($prefix);
            $indent = str_repeat(' ', mb_strlen($prefix));
        } else {
            $indent = '';
        }

        $lines = Regex::split('/\r\n|\n|\r/', $string);
        $count = count($lines);
        $lists = [];
        $lastWasItem = false;
        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            // Remove prefixes to ensure lists with the same heading are merged
            if (
                $prefix !== null
                && !$prefixIsItem
                && substr($line, 0, $prefixBytes) === $prefix
            ) {
                /** @var string */
                $line = substr($line, $prefixBytes);
            }

            // Clear the current heading if this is an empty line after an item
            if (trim($line) === '') {
                if (!$loose && $lastWasItem) {
                    unset($list);
                }
                continue;
            }

            if (Regex::match($regex, $line, $matches, \PREG_OFFSET_CAPTURE)) {
                // Collect subsequent lines with indentation of the same width
                if (
                    ($matches['indent'][1] ?? null) === 0
                    && ($itemIndent = $matches['indent'][0]) !== ''
                ) {
                    $itemIndent = self::expandTabs($itemIndent, $tabSize);
                    $itemIndentBytes = mb_strlen($itemIndent);
                    $itemIndent = str_repeat(' ', $itemIndentBytes);
                    $tentative = '';
                    $backtrack = 0;
                    while ($i < $count - 1) {
                        $nextLine = $lines[$i + 1];
                        if (trim($nextLine) === '') {
                            $tentative .= $nextLine . $eol;
                            $backtrack++;
                        } elseif (substr(self::expandTabs($nextLine, $tabSize), 0, $itemIndentBytes) === $itemIndent) {
                            $line .= $eol . $tentative . $nextLine;
                            $tentative = '';
                            $backtrack = 0;
                        } else {
                            $i -= $backtrack;
                            break;
                        }
                        $i++;
                    }
                }
            } else {
                $list = $line;
            }

            $key = $list ?? $line;
            $lists[$key] ??= [];
            $lastWasItem = $key !== $line;
            if ($lastWasItem && !in_array($line, $lists[$key], true)) {
                $lists[$key][] = $line;
            }
        }

        // Move top-level lines to the top
        $top = [];
        $itemList = null;
        foreach ($lists as $list => $lines) {
            if (count($lines)) {
                continue;
            }

            unset($lists[$list]);

            if ($discardEmpty && !Regex::match($regex, $list)) {
                continue;
            }

            if ($clean) {
                $top[$list] = [];
                continue;
            }

            // Move consecutive top-level items to their own list so
            // `$listSeparator` isn't inserted between them
            if (Regex::match($regex, $list)) {
                if ($itemList !== null) {
                    $top[$itemList][] = $list;
                    continue;
                }
                $itemList = $list;
            } else {
                $itemList = null;
            }
            $top[$list] = [];
        }
        $lists = $top + $lists;

        $merged = [];
        foreach ($lists as $list => $lines) {
            if ($clean) {
                $list = Regex::replace($regex, '', $list, 1);
            }

            if (
                $prefix !== null
                && !($prefixIsItem && substr($list, 0, $prefixBytes) === $prefix)
                && !Regex::match($regex, $list)
            ) {
                $list = $prefix . $list;
                $listHasPrefix = true;
            } else {
                $listHasPrefix = false;
            }

            if (!$lines) {
                $merged[] = $list;
                continue;
            }

            // Don't separate or indent consecutive top-level items
            if (!$listHasPrefix && Regex::match($regex, $list)) {
                $merged[] = implode($eol, [$list, ...$lines]);
                continue;
            }

            $merged[] = $list;
            $merged[] = $indent . implode($eol . $indent, $lines);
        }

        return implode($listSeparator, $merged);
    }
}
