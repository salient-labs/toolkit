<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Contract\IDateFormatter;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Support\DateFormatter;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Stringable;

/**
 * Convert data from one type/format/structure to another
 *
 * Examples:
 * - normalise alphanumeric text
 * - convert a list array to a map array
 * - pluralise a singular noun
 * - extract a class name from a FQCN
 */
final class Convert extends Utility
{
    /**
     * Convert a scalar to the type it appears to be
     *
     * @param mixed $value
     * @param bool $toFloat If `true` (the default), convert float strings to
     * `float`s.
     * @param bool $toBool If `true` (the default), convert boolean strings to
     * `bool`s.
     * @return int|float|string|bool|null
     */
    public static function toValue($value, bool $toFloat = true, bool $toBool = true)
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value)) {
            throw new LogicException('$value must be a scalar');
        }
        if (Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value)) {
            return (int) $value;
        }
        if ($toFloat && is_numeric($value)) {
            return (float) $value;
        }
        if ($toBool && Pcre::match(
            '/^' . Regex::BOOLEAN_STRING . '$/',
            $value,
            $match,
            \PREG_UNMATCHED_AS_NULL
        )) {
            return $match['true'] !== null;
        }
        return $value;
    }

    /**
     * Convert a value to a boolean, preserving null
     *
     * @param mixed $value
     * @see Test::isBoolValue()
     */
    public static function toBool($value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }
        if (is_string($value) && Pcre::match(
            '/^' . Regex::BOOLEAN_STRING . '$/',
            $value,
            $match,
            \PREG_UNMATCHED_AS_NULL
        )) {
            return $match['true'] !== null;
        }
        return (bool) $value;
    }

    /**
     * Convert a value to an integer, preserving null
     *
     * @param mixed $value
     */
    public static function toInt($value): ?int
    {
        if ($value === null) {
            return null;
        }
        return (int) $value;
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
                $expanded .= Pcre::replace('/(?<=\n|\G)\t/', $softTab, $line);
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
     * Convert an interval to the equivalent number of seconds
     *
     * Works with ISO 8601 durations like `PT48M`.
     *
     * @param DateInterval|string $value
     */
    public static function intervalToSeconds($value): int
    {
        if (!($value instanceof DateInterval)) {
            $value = new DateInterval($value);
        }
        $then = new DateTimeImmutable();
        $now = $then->add($value);

        return $now->getTimestamp() - $then->getTimestamp();
    }

    /**
     * Get the first value that is not null
     *
     * @param mixed ...$values
     * @return mixed
     */
    public static function coalesce(...$values)
    {
        while ($values) {
            $value = array_shift($values);
            if ($value !== null) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Remove the directory and up to the given number of extensions from a path
     *
     * @param int $extLimit If set, remove extensions matching the regular
     * expression `\.[^.\s]+$` unless `""`, `"."`, or `".."` would remain:
     * - `<0`: remove all extensions
     * - `>0`: remove up to the given number of extensions
     */
    public static function pathToBasename(string $path, int $extLimit = 0): string
    {
        $path = basename($path);
        if ($extLimit) {
            $range = $extLimit > 1 ? "{1,$extLimit}" : ($extLimit < 0 ? '+' : '');
            $path = Pcre::replace("/(?<=.)(?<!^\.|^\.\.)(\.[^.\s]+){$range}\$/", '', $path);
        }

        return $path;
    }

    /**
     * Replace the end of a multi-byte string with an ellipsis ("...") if its
     * length exceeds a limit
     */
    public static function ellipsize(string $value, int $length): string
    {
        if (mb_strlen($value) > $length) {
            return rtrim(mb_substr($value, 0, $length - 3)) . '...';
        }

        return $value;
    }

    /**
     * If $number is 1, return $singular, otherwise return $plural
     *
     * @param string|null $plural `"{$singular}s"` is used if `$plural` is
     * `null`.
     * @param bool $includeNumber If `true`, `"$number $noun"` is returned
     * instead of `"$noun"`.
     */
    public static function plural(int $number, string $singular, ?string $plural = null, bool $includeNumber = false): string
    {
        $noun = $number == 1
            ? $singular
            : ($plural === null ? $singular . 's' : $plural);

        return $includeNumber
            ? "$number $noun"
            : $noun;
    }

    /**
     * Get a phrase like "between lines 3 and 11" or "on platform 23"
     *
     * @param string|null $plural `"{$singular}s"` is used if `$plural` is
     * `null`.
     */
    public static function pluralRange(
        int $from,
        int $to,
        string $singular,
        ?string $plural = null,
        string $preposition = 'on'
    ): string {
        return $to - $from
            ? sprintf('between %s %d and %d', $plural === null ? $singular . 's' : $plural, $from, $to)
            : sprintf('%s %s %d', $preposition, $singular, $from);
    }

    /**
     * Get the plural of a singular noun
     */
    public static function nounToPlural(string $noun): string
    {
        if (Pcre::match('/(?:(sh?|ch|x|z|(?<!^phot)(?<!^pian)(?<!^hal)o)|([^aeiou]y)|(is)|(on))$/i', $noun, $matches)) {
            if ($matches[1]) {
                return $noun . 'es';
            } elseif ($matches[2]) {
                return substr_replace($noun, 'ies', -1);
            } elseif ($matches[3]) {
                return substr_replace($noun, 'es', -2);
            } elseif ($matches[4]) {
                return substr_replace($noun, 'a', -2);
            }
        }

        return $noun . 's';
    }

    /**
     * Convert a list of "key=value" strings to an array like ["key" => "value"]
     *
     * @param string[] $query
     * @return array<string,string>
     */
    public static function queryToData(array $query): array
    {
        // 1. "key=value" to ["key", "value"]
        // 2. Discard "value", "=value", etc.
        // 3. ["key", "value"] => ["key" => "value"]
        return array_column(
            array_filter(
                array_map(
                    fn(string $kv) => explode('=', $kv, 2),
                    $query
                ),
                fn(array $kv) => count($kv) == 2 && trim($kv[0])
            ),
            1,
            0
        );
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
    public static function linesToLists(
        string $text,
        string $separator = "\n",
        ?string $marker = null,
        string $regex = '/^(?<indent>\h*[-*] )/',
        bool $clean = false,
        bool $loose = false
    ): string {
        $marker = (string) $marker !== '' ? $marker . ' ' : null;
        $indent = $marker !== null ? str_repeat(' ', mb_strlen($marker)) : '';
        $markerIsItem = $marker !== null && Pcre::match($regex, $marker);

        /** @var array<string,string[]> */
        $sections = [];
        $lastWasItem = false;
        $lines = preg_split('/\r\n|\n|\r/', $text);
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
            if (Pcre::match($regex, $line, $matches)) {
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
            if (Pcre::match($regex, $section)) {
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
                $section = Pcre::replace($regex, '', $section, 1);
            }

            $marked = false;
            if ($marker !== null &&
                    !($markerIsItem && strpos($section, $marker) === 0) &&
                    !Pcre::match($regex, $section)) {
                $section = $marker . $section;
                $marked = true;
            }

            if (!$lines) {
                $groups[] = $section;
                continue;
            }

            // Don't separate or indent top-level list items collected above
            if (!$marked && Pcre::match($regex, $section)) {
                $groups[] = implode("\n", [$section, ...$lines]);
                continue;
            }

            $groups[] = $section;
            $groups[] = $indent . implode("\n" . $indent, $lines);
        }

        return implode($separator, $groups);
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
        $escapes = $ignoreEscapes ? '' : Regex::BEFORE_UNESCAPED . '\K';

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

        return Pcre::replace($search, $replace, $string);
    }

    /**
     * Convert a 16-byte UUID to its 36-byte hexadecimal representation
     */
    public static function uuidToHex(string $bytes): string
    {
        $uuid = [];
        $uuid[] = substr($bytes, 0, 4);
        $uuid[] = substr($bytes, 4, 2);
        $uuid[] = substr($bytes, 6, 2);
        $uuid[] = substr($bytes, 8, 2);
        $uuid[] = substr($bytes, 10, 6);

        return implode('-', array_map(fn(string $bin): string => bin2hex($bin), $uuid));
    }

    /**
     * Convert php.ini values like "128M" to bytes
     *
     * @param string $size From the PHP FAQ: "The available options are K (for
     * Kilobytes), M (for Megabytes) and G (for Gigabytes), and are all
     * case-insensitive."
     */
    public static function sizeToBytes(string $size): int
    {
        if (!Pcre::match('/^(.+?)([KMG]?)$/', Str::upper($size), $match) || !is_numeric($match[1])) {
            throw new LogicException("Invalid shorthand: '$size'");
        }

        $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3];

        return (int) ($match[1] * (1024 ** $power[$match[2]]));
    }

    /**
     * Convert the given strings and Stringables to an array of strings
     *
     * @param int|float|string|bool|Stringable|null ...$value
     * @return string[]
     */
    public static function toStrings(...$value): array
    {
        return array_map(function ($string) { return (string) $string; }, $value);
    }

    /**
     * Escape an argument for a POSIX-compatible shell
     */
    public static function toShellArg(string $arg): string
    {
        if ($arg === '' || Pcre::match('/[^a-z0-9+.\/@_-]/i', $arg)) {
            return "'" . str_replace("'", "'\''", $arg) . "'";
        }

        return $arg;
    }

    /**
     * Escape an argument for cmd.exe on Windows
     *
     * Derived from `Composer\Util\ProcessExecutor::escapeArgument()`, which
     * credits <https://github.com/johnstevenson/winbox-args>.
     */
    public static function toCmdArg(string $arg): string
    {
        $arg = Pcre::replace('/(\\\\*)"/', '$1$1\"', $arg, -1, $quoteCount);

        $quote = $arg === '' || strpbrk($arg, " \t,") !== false;
        $meta = $quoteCount > 0 || Pcre::match('/%[^%]+%|![^!]+!/', $arg);

        if (!$meta && !$quote) {
            $quote = strpbrk($arg, '^&|<>()') !== false;
        }

        if ($quote) {
            $arg = '"' . Pcre::replace('/(\\\\*)$/', '$1$1', $arg) . '"';
        }

        if ($meta) {
            $arg = Pcre::replace('/["^&|<>()%!]/', '^$0', $arg);
        }

        return $arg;
    }

    /**
     * Clean up a string for comparison with other strings
     *
     * This method is not guaranteed to be idempotent between releases.
     *
     * Here's what it currently does:
     * 1. Replaces ampersands (`&`) with ` and `
     * 2. Removes full stops (`.`)
     * 3. Replaces non-alphanumeric sequences with a space (` `)
     * 4. Trims leading and trailing spaces
     * 5. Makes letters uppercase
     */
    public static function toNormal(string $text): string
    {
        $replace = [
            '/(?<=[^&])&(?=[^&])/u' => ' and ',
            '/\.+/u' => '',
            '/[^[:alnum:]]+/u' => ' ',
        ];

        return Str::upper(trim(Pcre::replace(
            array_keys($replace),
            array_values($replace),
            $text
        )));
    }

    /**
     * A wrapper for get_object_vars
     *
     * Because you can't exclude `private` and `protected` properties from
     * inside the class. (Not easily, anyway.)
     *
     * @return mixed[]
     */
    public static function objectToArray(object $object): array
    {
        return get_object_vars($object);
    }

    /**
     * @param mixed[] $data
     */
    private static function _dataToQuery(
        array $data,
        bool $preserveKeys,
        IDateFormatter $dateFormatter,
        ?string &$query = null,
        string $name = '',
        string $format = '%s'
    ): string {
        if ($query === null) {
            $query = '';
        }

        foreach ($data as $param => $value) {
            $_name = sprintf($format, $param);

            if (!is_array($value)) {
                if (is_bool($value)) {
                    $value = (int) $value;
                } elseif ($value instanceof DateTimeInterface) {
                    $value = $dateFormatter->format($value);
                }

                $query .= ($query ? '&' : '') . rawurlencode($name . $_name) . '=' . rawurlencode((string) $value);

                continue;
            } elseif (!$preserveKeys && Arr::isList($value, true)) {
                $_format = '[]';
            } else {
                $_format = '[%s]';
            }

            self::_dataToQuery($value, $preserveKeys, $dateFormatter, $query, $name . $_name, $_format);
        }

        return $query;
    }

    /**
     * A more API-friendly http_build_query
     *
     * Booleans are cast to integers (`0` or `1`), `DateTime`s are formatted by
     * `$dateFormatter`, and other values are cast to string.
     *
     * Arrays with consecutive integer keys numbered from 0 are considered to be
     * lists. By default, keys are not included when adding lists to query
     * strings. Set `$preserveKeys` to override this behaviour.
     *
     * @param mixed[] $data
     */
    public static function dataToQuery(
        array $data,
        bool $preserveKeys = false,
        ?IDateFormatter $dateFormatter = null
    ): string {
        return self::_dataToQuery(
            $data,
            $preserveKeys,
            $dateFormatter ?: new DateFormatter()
        );
    }

    /**
     * Like var_export but with more compact output
     *
     * Indentation is applied automatically if `$delimiter` contains one or more
     * newline characters.
     *
     * Array keys are suppressed for list arrays.
     *
     * @param mixed $value
     * @param string $delimiter Added between array elements.
     * @param string $arrow Added between array keys and values.
     * @param string|null $escapeCharacters Characters to escape in hexadecimal
     * notation.
     */
    public static function valueToCode(
        $value,
        string $delimiter = ', ',
        string $arrow = ' => ',
        ?string $escapeCharacters = null,
        string $tab = '    '
    ): string {
        $eol = Get::eol($delimiter) ?: '';
        $multiline = (bool) $eol;
        return self::doValueToCode(
            $value,
            $delimiter,
            $arrow,
            $escapeCharacters,
            $tab,
            $multiline,
            $eol,
        );
    }

    /**
     * @param mixed $value
     */
    private static function doValueToCode(
        $value,
        string $delimiter,
        string $arrow,
        ?string $escapeCharacters,
        string $tab,
        bool $multiline,
        string $eol,
        string $indent = ''
    ): string {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value) &&
            (Pcre::match('/\v/', $value) ||
                ($escapeCharacters !== null &&
                    strpbrk($value, $escapeCharacters) !== false))) {
            $characters = "\0..\x1f\$\\" . $escapeCharacters;
            $escaped = addcslashes($value, $characters);

            // Escape explicitly requested characters in hexadecimal notation
            if ($escapeCharacters !== null) {
                $search = [];
                $replace = [];
                foreach (str_split($escapeCharacters) as $character) {
                    $search[] = sprintf('/((?<!\\\\)(?:\\\\\\\\)*)%s/', preg_quote(addcslashes($character, $character), '/'));
                    $replace[] = sprintf('$1\x%02x', ord($character));
                }
                $escaped = Pcre::replace($search, $replace, $escaped);
            }

            // Convert octal notation to hexadecimal and correct for differences
            // between C and PHP escape sequences:
            // - recognised by PHP: \0 \e \f \n \r \t \v
            // - returned by addcslashes: \000 \033 \a \b \f \n \r \t \v
            Pcre::replaceCallback(
                '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?<octal>[0-7]{3})|(?<cslash>[ab]))/',
                fn(array $matches) =>
                    $matches[1]
                    . ($matches['octal'] !== null
                        ? (($dec = octdec($matches['octal']))
                            ? ($dec === 27 ? '\e' : sprintf('\x%02x', $dec))
                            : '\0')
                        : sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']])),
                $escaped,
                -1,
                $count,
                \PREG_UNMATCHED_AS_NULL,
            );

            return '"' . $escaped . '"';
        }

        if (!is_array($value)) {
            return var_export($value, true);
        }

        if (!$value) {
            return '[]';
        }

        $prefix = '[';
        $suffix = ']';
        $glue = $delimiter;

        if ($multiline) {
            $suffix = "{$delimiter}{$indent}{$suffix}";
            $indent .= $tab;
            $prefix .= "{$eol}{$indent}";
            $glue .= $indent;
        }

        if (Arr::isList($value)) {
            foreach ($value as $value) {
                $values[] = self::doValueToCode(
                    $value,
                    $delimiter,
                    $arrow,
                    $escapeCharacters,
                    $tab,
                    $multiline,
                    $eol,
                    $indent,
                );
            }
        } else {
            foreach ($value as $key => $value) {
                $values[] = self::doValueToCode(
                    $key,
                    $delimiter,
                    $arrow,
                    $escapeCharacters,
                    $tab,
                    $multiline,
                    $eol,
                    $indent,
                ) . $arrow . self::doValueToCode(
                    $value,
                    $delimiter,
                    $arrow,
                    $escapeCharacters,
                    $tab,
                    $multiline,
                    $eol,
                    $indent,
                );
            }
        }

        return $prefix . implode($glue, $values) . $suffix;
    }
}
