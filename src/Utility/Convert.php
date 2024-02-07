<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Support\Date\DateFormatter;
use Lkrms\Support\Date\DateFormatterInterface;
use DateTimeInterface;
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
        DateFormatterInterface $dateFormatter,
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
        ?DateFormatterInterface $dateFormatter = null
    ): string {
        return self::_dataToQuery(
            $data,
            $preserveKeys,
            $dateFormatter ?: new DateFormatter()
        );
    }
}
