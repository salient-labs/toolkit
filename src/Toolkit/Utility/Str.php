<?php declare(strict_types=1);

namespace Salient\Utility;

use Salient\Utility\Internal\ListMerger;
use Closure;
use InvalidArgumentException;
use Stringable;

/**
 * Work with strings
 *
 * @api
 */
final class Str extends AbstractUtility
{
    public const ALPHANUMERIC = Str::ALPHA . Str::NUMERIC;
    public const ALPHA = Str::LOWER . Str::UPPER;
    public const LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const NUMERIC = '0123456789';
    public const HEX = '0123456789abcdefABCDEF';
    public const PRESERVE_DOUBLE_QUOTED = 1;
    public const PRESERVE_SINGLE_QUOTED = 2;
    public const PRESERVE_QUOTED = Str::PRESERVE_DOUBLE_QUOTED | Str::PRESERVE_SINGLE_QUOTED;

    public const ASCII_EXTENDED =
        "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f"
        . "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f"
        . "\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf"
        . "\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf"
        . "\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf"
        . "\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf"
        . "\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef"
        . "\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";

    /**
     * Default value of mergeLists() parameter $itemRegex
     */
    public const DEFAULT_ITEM_REGEX = '/^(?<indent>\h*[-*] )/';

    /**
     * Get the first string that is not null or empty, or return the last value
     *
     * @param int|float|string|bool|Stringable|null ...$strings
     */
    public static function coalesce(...$strings): ?string
    {
        $string = null;
        foreach ($strings as $string) {
            if ($string !== null) {
                $string = (string) $string;
                if ($string !== '') {
                    return $string;
                }
            }
        }
        return $string;
    }

    /**
     * Convert ASCII letters in a string to lowercase
     */
    public static function lower(string $string): string
    {
        return strtr($string, self::UPPER, self::LOWER);
    }

    /**
     * Convert ASCII letters in a string to uppercase
     */
    public static function upper(string $string): string
    {
        return strtr($string, self::LOWER, self::UPPER);
    }

    /**
     * Make the first character in a string uppercase if it is an ASCII letter
     */
    public static function upperFirst(string $string): string
    {
        if ($string !== '') {
            $string[0] = self::upper($string[0]);
        }
        return $string;
    }

    /**
     * Match a string's case to another string
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

        if (strlen($match) === 1) {
            return $hasLower
                ? self::lower($string)
                : ($hasUpper
                    ? self::upperFirst(self::lower($string))
                    : $string);
        }

        if ($hasUpper && !$hasLower) {
            return self::upper($string);
        }

        if (!$hasUpper && $hasLower) {
            return self::lower($string);
        }

        // Do nothing if there are no letters, or if there is a mix of cases and
        // the first letter is not uppercase
        if ((!$hasUpper && !$hasLower) || $upper !== $match) {
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
            if ($needle !== '' && substr($haystack, 0, strlen($needle)) === $needle) {
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
     * Escape special characters in a string for use in Markdown
     */
    public static function escapeMarkdown(string $string): string
    {
        return Regex::replace(
            <<<'REGEX'
/ [*<[\\`|] |
  (?<= [\h[:punct:]] (?: (?<! _ ) | (?<= \G ) ) | ^ ) _ |
  _ (?= _*+ (?: [\h[:punct:]] | $ | \R ) ) |
  (?<! ~ ) ~ (?= ~ (?! ~ ) ) |
  ^ \h* \K (?: > | ~ (?= ~~+ ) | (?: \# {1,6} | [+-] | [0-9]+ \K \. ) (?= \h ) ) /mx
REGEX,
            '\\\\$0',
            $string,
        );
    }

    /**
     * Normalise a string for comparison
     *
     * The return value of this method is not covered by the Salient toolkit's
     * backward compatibility promise.
     */
    public static function normalise(string $string): string
    {
        // 1. Replace "&" with " and "
        // 2. Remove "."
        // 3. Replace non-alphanumeric character sequences with " "
        // 4. Remove leading and trailing whitespace
        // 5. Convert ASCII characters to uppercase
        return self::upper(trim(Regex::replace([
            '/([[:alnum:]][^&]*+)&(?=[^&[:alnum:]]*+[[:alnum:]])/u',
            '/\.++/',
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
     *
     * @param int<3,max> $length
     */
    public static function ellipsize(string $value, int $length): string
    {
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
            // Don't remove "\n" from "\r\n"
            if ($s !== $string && $s !== '' && $s[-1] === "\r") {
                return "$s\n";
            }
            return $s;
        }

        $length = strlen(\PHP_EOL);
        while (substr($string, -$length) === \PHP_EOL) {
            $string = substr($string, 0, -$length);
        }

        return $string;
    }

    /**
     * Replace line feed (LF) characters in a string with the native end-of-line
     * sequence
     */
    public static function eolToNative(string $string): string
    {
        return \PHP_EOL === "\n"
            ? $string
            : str_replace("\n", \PHP_EOL, $string);
    }

    /**
     * Replace native end-of-line sequences in a string with the line feed (LF)
     * character
     */
    public static function eolFromNative(string $string): string
    {
        return \PHP_EOL === "\n"
            ? $string
            : str_replace(\PHP_EOL, "\n", $string);
    }

    /**
     * Convert words in a string to snake_case, optionally preserving non-word
     * characters
     */
    public static function snake(string $string, string $preserve = ''): string
    {
        return self::lower(self::words($string, '_', $preserve));
    }

    /**
     * Convert words in a string to kebab-case, optionally preserving non-word
     * characters
     */
    public static function kebab(string $string, string $preserve = ''): string
    {
        return self::lower(self::words($string, '-', $preserve));
    }

    /**
     * Convert words in a string to camelCase, optionally preserving non-word
     * characters
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
     * Convert words in a string to PascalCase, optionally preserving non-word
     * characters
     */
    public static function pascal(string $string, string $preserve = ''): string
    {
        return self::words($string, '', $preserve, fn($string) => self::upperFirst(self::lower($string)));
    }

    /**
     * Get words from a string and delimit them with a separator, optionally
     * preserving non-word characters and applying a callback to each word
     *
     * A word consists of one or more letters of the same case, or one uppercase
     * letter followed by zero or more lowercase letters. Numbers are treated as
     * lowercase letters except that two or more uppercase letters form one word
     * with any subsequent numbers.
     *
     * @param (Closure(string): string)|null $callback
     */
    public static function words(
        string $string,
        string $separator = ' ',
        string $preserve = '',
        ?Closure $callback = null
    ): string {
        $notAfterPreserve = '';
        if (
            $preserve !== ''
            && ($preserve = Regex::replace('/[[:alnum:]]++/u', '', $preserve)) !== ''
        ) {
            $preserve = Regex::quoteCharacters($preserve, '/');
            $preserve = "[:alnum:]{$preserve}";
            // Prevent "key=value" becoming "key= value" when preserving "=" by
            // asserting that when separating words, they must appear:
            // - immediately after the previous word (\G),
            // - after an unpreserved character, or
            // - at a word boundary (e.g. "Value" in "key=someValue")
            if ($separator !== '') {
                $notAfterPreserve = '(?:\G'
                    . "|(?<=[^{$preserve}])"
                    . '|(?<=[[:lower:][:digit:]])(?=[[:upper:]]))';
            }
        } else {
            $preserve = '[:alnum:]';
        }
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
            "/^[^{$preserve}]++|[^{$preserve}]++\$/uD",
            "/[^{$preserve}]++/u",
        ], [
            '',
            $separator,
        ], $string);
    }

    /**
     * Expand tabs in a string to spaces
     *
     * @param int<1,max> $tabSize
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
        $lines = Regex::split('/(\r\n|\n|\r)/', $string, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $lines[] = '';
        $expanded = '';
        foreach (array_chunk($lines, 2) as [$line, $eol]) {
            $parts = explode("\t", $line);
            $last = array_key_last($parts);
            foreach ($parts as $i => $part) {
                $expanded .= $part;
                if ($i === $last) {
                    $expanded .= $eol;
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
     * @param int<1,max> $tabSize
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
        $lines = Regex::split('/(\r\n|\n|\r)/', $string, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $lines[] = '';
        $expanded = '';
        foreach (array_chunk($lines, 2) as $i => [$line, $eol]) {
            if (!$i && $preserveLine1) {
                $expanded .= $line . $eol;
                $column = 1;
                continue;
            }
            $parts = explode("\t", $line);
            do {
                $part = array_shift($parts);
                $expanded .= $part;
                if (!$parts) {
                    $expanded .= $eol;
                    break;
                }
                if ($part !== '' && trim($part, ' ') !== '') {
                    $expanded .= "\t" . implode("\t", $parts) . $eol;
                    break;
                }
                $column += mb_strlen($part);
                $spaces = $tabSize - (($column - 1) % $tabSize);
                $expanded .= str_repeat(' ', $spaces);
                $column += $spaces;
            } while (true);
            $column = 1;
        }
        return $expanded;
    }

    /**
     * Copy a string to a php://temp stream
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
     * @param int|null $limit The maximum number of substrings to return.
     * Implies `$removeEmpty = false` if not `null`.
     * @param string|null $characters Characters to trim, `null` (the default)
     * to trim whitespace, or an empty string to trim nothing.
     * @return ($limit is null ? ($removeEmpty is true ? list<string> : non-empty-list<string>) : non-empty-list<string>)
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
        $split = explode($separator, $string, $limit ?? \PHP_INT_MAX);
        $split = Arr::trim($split, $characters, $removeEmpty);
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

        $quoted = Regex::quote($separator, '/');
        $escaped = Regex::quoteCharacters($separator, '/');
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
        $regex = Regex::delimit($regex, '/');
        Regex::matchAll($regex, $string, $matches);
        $split = Arr::trim($matches[0], $characters, $removeEmpty);

        // @phpstan-ignore return.type
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

        return !$delta
            ? wordwrap($string, $width, $break, $cutLongWords)
            : ($delta < 0
                // For hanging indents, remove and restore $delta characters
                ? substr($string, 0, -$delta)
                    . wordwrap(substr($string, -$delta), $width, $break, $cutLongWords)
                // For first line indents, add and remove $delta characters
                : substr(
                    wordwrap(str_repeat('x', $delta) . $string, $width, $break, $cutLongWords),
                    $delta,
                ));
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
        $newline = Regex::quote($break, '/');
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
        ) / max(strlen($string1), strlen($string2));
    }

    /**
     * Get ngrams shared between two strings relative to the number of ngrams in
     * the longest string
     *
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
     * Get ngrams shared between two strings relative to the number of ngrams in
     * the shortest string
     *
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
            /** @var string[] */
            $split = str_split($split, $size);
            $ngrams = array_merge($ngrams, $split);
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
     * - Top-level lines (headings with no items, and items with no heading) are
     *   returned before lists with headings
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
     * @param int<1,max> $tabSize
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
        return (new ListMerger(
            $listSeparator,
            self::coalesce($headingPrefix, null),
            $itemRegex ?? self::DEFAULT_ITEM_REGEX,
            $clean,
            $loose,
            $discardEmpty,
            $eol,
            $tabSize,
        ))->merge($string);
    }
}
