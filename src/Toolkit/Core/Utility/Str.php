<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use InvalidArgumentException;

/**
 * Work with strings
 */
final class Str extends AbstractUtility
{
    public const ALPHA = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const ALPHANUMERIC = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    public const HEX = '0123456789abcdefABCDEF';
    public const LOWER = 'abcdefghijklmnopqrstuvwxyz';
    public const NUMERIC = '0123456789';
    public const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const PRESERVE_DOUBLE_QUOTED = 1;
    public const PRESERVE_SINGLE_QUOTED = 2;
    public const PRESERVE_QUOTED = Str::PRESERVE_DOUBLE_QUOTED | Str::PRESERVE_SINGLE_QUOTED;

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
     * Convert ASCII alphabetic characters in a string to lowercase
     */
    public static function lower(string $string): string
    {
        return strtr($string, self::UPPER, self::LOWER);
    }

    /**
     * Convert ASCII alphabetic characters in a string to uppercase
     */
    public static function upper(string $string): string
    {
        return strtr($string, self::LOWER, self::UPPER);
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
            // @phpstan-ignore-next-line
            (!$hasUpper && !$hasLower)
            || $upper !== $match
        ) {
            return $string;
        }

        return self::upperFirst(self::lower($string));
    }

    /**
     * Normalise a string for comparison
     *
     * This method performs the following operations:
     *
     * 1. Replace ampersands (`&`) with ` and `
     * 2. Remove full stops (`.`)
     * 3. Replace non-alphanumeric sequences with a space (` `)
     * 4. Trim leading and trailing spaces
     * 5. Make letters uppercase
     */
    public static function normalise(string $string): string
    {
        $replace = [
            '/(?<=[^&])&(?=[^&])/' => ' and ',
            '/\.++/' => '',
            '/[^[:alnum:]]+/u' => ' ',
        ];

        return self::upper(trim(Regex::replace(
            array_keys($replace),
            array_values($replace),
            $string
        )));
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
     *
     * @template T of string|null
     *
     * @param T $string
     * @return T
     */
    public static function trimNativeEol(?string $string): ?string
    {
        if ($string === null || $string === '') {
            return $string;
        }

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
     * Replace newlines in a string with native end-of-line sequences
     *
     * @template T of string|null
     *
     * @param T $string
     * @return T
     */
    public static function eolToNative(?string $string): ?string
    {
        // @phpstan-ignore-next-line
        return $string === null
            ? null
            : (\PHP_EOL === "\n"
                ? $string
                : str_replace("\n", \PHP_EOL, $string));
    }

    /**
     * Replace native end-of-line sequences in a string with newlines
     *
     * @template T of string|null
     *
     * @param T $string
     * @return T
     */
    public static function eolFromNative(?string $string): ?string
    {
        // @phpstan-ignore-next-line
        return $string === null
            ? null
            : (\PHP_EOL === "\n"
                ? $string
                : str_replace(\PHP_EOL, "\n", $string));
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
        return Regex::replaceCallback(
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
        if ($preserve !== null && $preserve !== '') {
            $preserve = Regex::replace('/[[:alnum:]]/u', '', $preserve);
            if ($preserve !== '') {
                // Prevent "key=value" becoming "key= value" when preserving "="
                // by asserting that when separating words, they must appear:
                // - immediately after the previous word (\G)
                // - after an unpreserved character, or
                // - at a word boundary (e.g. "Value" in "key=someValue")
                $preserve = Regex::quoteCharacterClass($preserve, '/');
                $notAfterPreserve = "(?:\G|(?<=[^[:alnum:]{$preserve}])|(?<=[[:lower:][:digit:]])(?=[[:upper:]]))";
            }
        }
        $preserve = "[:alnum:]{$preserve}";
        $word = '(?:[[:upper:]]?[[:lower:][:digit:]]+|(?:[[:upper:]](?![[:lower:]]))+[[:digit:]]*)';

        // Insert separators before words not adjacent to a preserved character
        // to prevent "foo bar" becoming "foobar", for example
        if ($separator !== '') {
            $string = Regex::replace(
                "/$notAfterPreserve$word/u",
                $separator . '$0',
                $string
            );
        }

        if ($callback !== null) {
            $string = Regex::replaceCallback(
                "/$word/u",
                fn(array $match): string => $callback($match[0]),
                $string
            );
        }

        // Trim unpreserved characters from the beginning and end of the string,
        // then replace sequences of one or more unpreserved characters with one
        // separator
        $string = Regex::replace([
            "/^[^{$preserve}]++|[^{$preserve}]++\$/u",
            "/[^{$preserve}]++/u",
        ], [
            '',
            $separator,
        ], $string);

        return $string;
    }

    /**
     * Expand tabs to spaces
     */
    public static function expandTabs(
        string $text,
        int $tabSize = 8,
        int $column = 1
    ): string {
        if (strpos($text, "\t") === false) {
            return $text;
        }
        $eol = Get::eol($text) ?: "\n";
        $expanded = '';
        foreach (explode($eol, $text) as $i => $line) {
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
     * Expand leading tabs to spaces
     */
    public static function expandLeadingTabs(
        string $text,
        int $tabSize = 8,
        bool $preserveLine1 = false,
        int $column = 1
    ): string {
        if (strpos($text, "\t") === false) {
            return $text;
        }
        $eol = Get::eol($text) ?: "\n";
        $softTab = str_repeat(' ', $tabSize);
        $expanded = '';
        foreach (explode($eol, $text) as $i => $line) {
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
                if ($part) {
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
        File::write($stream, $string);
        File::rewind($stream);
        return $stream;
    }

    /**
     * Split a string by a string and remove whitespace from the beginning and
     * end of each substring before removing empty strings
     *
     * @param non-empty-string $separator
     * @param int|null $limit Limit the number of substrings returned. Implies
     * `$removeEmpty = false`.
     * @param string|null $characters Specify characters to trim instead of
     * whitespace. If an empty string is given, substrings are not trimmed.
     * @return list<string>
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
     * Without splitting bracket-delimited or double-quoted substrings, split a
     * string by a string and remove whitespace from the beginning and end of
     * each substring before optionally removing empty strings
     *
     * @param non-empty-string $separator
     * @param string|null $characters Specify characters to trim instead of
     * whitespace. If an empty string is given, substrings are not trimmed.
     * @param int-mask-of<Str::PRESERVE_*> $flags
     * @return ($removeEmpty is true ? list<string> : non-empty-list<string>)
     */
    public static function splitDelimited(
        string $separator,
        string $string,
        bool $removeEmpty = false,
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
            $regex .= "|\n" . '    " (?: [^"\\\\] | \\\\ . )*+ " ';
        }
        if ($flags & self::PRESERVE_SINGLE_QUOTED) {
            $quotes .= "'";
            $regex .= "|\n" . "    ' (?: [^'\\\\] | \\\\ . )*+ ' ";
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
    \{ (?: [^{$quotes}()<>[\]{}]*+ (?-1)? )*+ \} {$regex}) |
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
     * Undo wordwrap(), preserving Markdown-style paragraphs and lists
     *
     * Non-consecutive line breaks are converted to spaces unless they precede
     * one of the following:
     *
     * - four or more spaces
     * - one or more tabs
     * - a Markdown-style list item (e.g. `- item`, `1. item`)
     *
     * If `$ignoreEscapes` is `false`, whitespace escaped with a backslash is
     * preserved.
     *
     * If `$trimTrailingWhitespace` is `true`, whitespace is removed from the
     * end of each line, and if `$collapseBlankLines` is `true`, three or more
     * subsequent line breaks are collapsed to two.
     */
    public static function unwrap(
        string $string,
        string $break = "\n",
        bool $ignoreEscapes = true,
        bool $trimTrailingWhitespace = false,
        bool $collapseBlankLines = false
    ): string {
        $newline = preg_quote($break, '/');
        $escapes = $ignoreEscapes ? '' : '(?<!\\\\)(?:\\\\\\\\)*\K';

        if ($trimTrailingWhitespace) {
            $search[] = "/{$escapes}\h+{$newline}/";
            $replace[] = $break;
        }

        $search[] = "/{$escapes}(?<!{$newline}){$newline}(?!{$newline}|    |\\t|(?:[-+*]|[0-9]+[).])\h)/";
        $replace[] = ' ';

        if ($collapseBlankLines) {
            $search[] = "/(?:{$newline}){3,}/";
            $replace[] = $break . $break;
        }

        return Regex::replace($search, $replace, $string);
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

    /**
     * Get the Levenshtein distance between two strings relative to the length
     * of the longest string
     *
     * @param bool $normalise If true, normalise `$string1` and `$string2` with
     * {@see Str::normalise()} before comparing them.
     * @return float A value between `0` and `1`, where `0` means the strings
     * are identical, and `1` means they have no similarities.
     */
    public static function distance(
        string $string1,
        string $string2,
        bool $normalise = true
    ): float {
        if ($string1 === '' && $string2 === '') {
            return 0.0;
        }

        if ($normalise) {
            $string1 = self::normalise($string1);
            $string2 = self::normalise($string2);
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
    public static function similarity(
        string $string1,
        string $string2,
        bool $normalise = true
    ): float {
        if ($string1 === '' && $string2 === '') {
            return 1.0;
        }

        if ($normalise) {
            $string1 = self::normalise($string1);
            $string2 = self::normalise($string2);
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
            return 1.0;
        }

        if ($normalise) {
            $string1 = self::normalise($string1);
            $string2 = self::normalise($string2);
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

    /**
     * Remove duplicates in a string where top-level lines ("sections") are
     * grouped with "list items" below
     *
     * Lines that match `$regex` are regarded as list items, and other lines are
     * used as the section name for subsequent list items. If `$loose` is
     * `false` (the default), blank lines between list items clear the current
     * section name.
     *
     * Top-level lines with no children, including any list items orphaned by
     * blank lines above them, are returned before sections with children.
     *
     * If a named subpattern in `$regex` called `indent` matches a non-empty
     * string, subsequent lines with the same number of spaces for indentation
     * as there are characters in the match are treated as part of the item,
     * including any blank lines.
     *
     * Line endings used in `$text` may be any combination of LF, CRLF and CR,
     * but LF (`"\n"`) line endings are used in the return value.
     *
     * @param string $separator Used between top-level lines and sections. Has
     * no effect on the end-of-line sequence used between items, which is always
     * LF (`"\n"`).
     * @param string|null $marker Added before each section name. Nested list
     * items are indented by the equivalent number of spaces. To add a leading
     * `"- "` to top-level lines and indent others with two spaces, set
     * `$marker` to `"-"`.
     * @param bool $clean If `true`, the first match of `$regex` in each section
     * name is removed.
     * @param bool $loose If `true`, blank lines between list items are ignored.
     */
    public static function mergeLists(
        string $text,
        string $separator = "\n",
        ?string $marker = null,
        string $regex = '/^(?<indent>\h*[-*] )/',
        bool $clean = false,
        bool $loose = false
    ): string {
        $marker = (string) $marker !== '' ? $marker . ' ' : null;
        $indent = $marker !== null ? str_repeat(' ', mb_strlen($marker)) : '';
        $markerIsItem = $marker !== null && Regex::match($regex, $marker);

        /** @var array<string,string[]> */
        $sections = [];
        $lastWasItem = false;
        $lines = Regex::split('/\r\n|\n|\r/', $text);
        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            // Remove pre-existing markers early to ensure sections with the
            // same name are combined
            if ($marker !== null && !$markerIsItem && strpos($line, $marker) === 0) {
                $line = substr($line, strlen($marker));
            }

            // Treat blank lines between items as section breaks
            if (trim($line) === '') {
                if (!$loose && $lastWasItem) {
                    unset($section);
                }
                continue;
            }

            // Collect any subsequent indented lines
            if (Regex::match($regex, $line, $matches)) {
                $matchIndent = $matches['indent'] ?? '';
                if ($matchIndent !== '') {
                    $matchIndent = str_repeat(' ', mb_strlen($matchIndent));
                    $pendingWhitespace = '';
                    $backtrack = 0;
                    while ($i < count($lines) - 1) {
                        $nextLine = $lines[$i + 1];
                        if (trim($nextLine) === '') {
                            $pendingWhitespace .= $nextLine . "\n";
                            $backtrack++;
                        } elseif (substr($nextLine, 0, strlen($matchIndent)) === $matchIndent) {
                            $line .= "\n" . $pendingWhitespace . $nextLine;
                            $pendingWhitespace = '';
                            $backtrack = 0;
                        } else {
                            $i -= $backtrack;
                            break;
                        }
                        $i++;
                    }
                }
            } else {
                $section = $line;
            }

            $key = $section ?? $line;

            if (!array_key_exists($key, $sections)) {
                $sections[$key] = [];
            }

            if ($key !== $line) {
                if (!in_array($line, $sections[$key])) {
                    $sections[$key][] = $line;
                }
                $lastWasItem = true;
            } else {
                $lastWasItem = false;
            }
        }

        // Move lines with no associated list to the top
        /** @var array<string,string[]> */
        $top = [];
        $last = null;
        foreach ($sections as $section => $lines) {
            if (count($lines)) {
                continue;
            }

            unset($sections[$section]);

            if ($clean) {
                $top[$section] = [];
                continue;
            }

            // Collect second and subsequent consecutive top-level list items
            // under the first so they don't form a loose list
            if (Regex::match($regex, $section)) {
                if ($last !== null) {
                    $top[$last][] = $section;
                    continue;
                }
                $last = $section;
            } else {
                $last = null;
            }
            $top[$section] = [];
        }
        /** @var array<string,string[]> */
        $sections = array_merge($top, $sections);

        $groups = [];
        foreach ($sections as $section => $lines) {
            if ($clean) {
                $section = Regex::replace($regex, '', $section, 1);
            }

            $marked = false;
            if ($marker !== null
                    && !($markerIsItem && strpos($section, $marker) === 0)
                    && !Regex::match($regex, $section)) {
                $section = $marker . $section;
                $marked = true;
            }

            if (!$lines) {
                $groups[] = $section;
                continue;
            }

            // Don't separate or indent top-level list items collected above
            if (!$marked && Regex::match($regex, $section)) {
                $groups[] = implode("\n", [$section, ...$lines]);
                continue;
            }

            $groups[] = $section;
            $groups[] = $indent . implode("\n" . $indent, $lines);
        }

        return implode($separator, $groups);
    }
}
