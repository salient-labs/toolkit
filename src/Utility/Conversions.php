<?php

declare(strict_types=1);

namespace Lkrms\Utility;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Iterator;
use IteratorIterator;
use Lkrms\Facade\Test;
use Lkrms\Support\DateFormatter;
use UnexpectedValueException;

/**
 * Convert data from one type/format/structure to another
 *
 * Examples:
 * - normalise alphanumeric text
 * - convert a list array to a map array
 * - pluralise a singular noun
 * - extract a class name from a FQCN
 */
final class Conversions
{
    /**
     * "snake_case"
     */
    public const IDENTIFIER_CASE_SNAKE = 0;

    /**
     * "kebab-case"
     */
    public const IDENTIFIER_CASE_KEBAB = 1;

    /**
     * "PascalCase"
     */
    public const IDENTIFIER_CASE_PASCAL = 2;

    /**
     * "camelCase"
     */
    public const IDENTIFIER_CASE_CAMEL = 3;

    /**
     * Cast a value to an integer, preserving null
     *
     */
    public function toIntOrNull($value): ?int
    {
        return is_null($value) ? null : (int)$value;
    }

    /**
     * If a value isn't an array, make it the first element of one
     *
     * @param mixed $value
     * @param bool $emptyIfNull
     * @return array Either `$value`, `[$value]`, or `[]` (only if
     * `$emptyIfNull` is set and `$value` is `null`).
     */
    public function toArray($value, bool $emptyIfNull = false): array
    {
        return is_array($value)
            ? $value
            : ($emptyIfNull && is_null($value) ? [] : [$value]);
    }

    /**
     * If a value isn't a list, make it the first element of one
     *
     * @param mixed $value
     * @param bool $emptyIfNull
     * @return array Either `$value`, `[$value]`, or `[]` (only if
     * `$emptyIfNull` is set and `$value` is `null`).
     */
    public function toList($value, bool $emptyIfNull = false): array
    {
        return Test::isListArray($value, true)
            ? $value
            : ($emptyIfNull && is_null($value) ? [] : [$value]);
    }

    /**
     * Recursively remove outer single-element arrays
     *
     * Example:
     *
     * ```php
     * var_export([
     *     Convert::flatten([[['id' => 1]]]),
     *     Convert::flatten(['nested scalar']),
     *     Convert::flatten(['nested associative' => 1]),
     *     Convert::flatten('plain scalar'),
     * ]);
     * ```
     *
     * Output:
     *
     * ```php
     * array (
     *   0 =>
     *   array (
     *     'id' => 1,
     *   ),
     *   1 => 'nested scalar',
     *   2 =>
     *   array (
     *     'nested associative' => 1,
     *   ),
     *   3 => 'plain scalar',
     * )
     * ```
     */
    public function flatten($value)
    {
        if (!is_array($value) || count($value) !== 1 || array_key_first($value) !== 0)
        {
            return $value;
        }

        return $this->flatten(reset($value));
    }

    /**
     * A type-agnostic array_unique with reindexing
     *
     */
    public function toUniqueList(array $array): array
    {
        $list = [];
        foreach ($array as $value)
        {
            if (in_array($value, $list, true))
            {
                continue;
            }
            $list[] = $value;
        }

        return $list;
    }

    /**
     * A faster array_unique with reindexing
     *
     * @param string[] $array
     * @return string[]
     */
    public function stringsToUniqueList(array $array): array
    {
        $list = [];
        $seen = [];
        foreach ($array as $value)
        {
            if ($seen[$value] ?? null)
            {
                continue;
            }
            $list[]       = $value;
            $seen[$value] = true;
        }

        return $list;
    }

    /**
     * JSON-encode non-scalar values in an array
     *
     * @return array<int,int|float|string|bool|null>
     */
    public function toScalarArray(array $array): array
    {
        foreach ($array as &$value)
        {
            if (is_scalar($value) || is_null($value))
            {
                continue;
            }
            $value = json_encode($value);
        }

        return $array;
    }

    /**
     * Explode a string, trim each substring, remove empty strings
     *
     * @return string[]
     */
    public function stringToList(string $separator, string $string, ?string $trim = null): array
    {
        if (!$separator)
        {
            throw new UnexpectedValueException("Invalid separator: $separator");
        }

        return array_values(array_filter(array_map(
            (is_null($trim)
                ? fn(string $item) => trim($item)
                : fn(string $item) => trim($item, $trim)),
            explode($separator, $string)
        )));
    }

    /**
     * Get the offset of a key in an array
     *
     * @param string|int $key
     * @return int|null `null` if `$key` is not found in `$array`.
     */
    public function arrayKeyToOffset($key, array $array): ?int
    {
        return array_flip(array_keys($array))[$key] ?? null;
    }

    /**
     * array_splice for associative arrays
     *
     * Removes `$length` values from the array, starting with `$array[$key]`,
     * and replaces the removed portion with the elements of `$replacement`.
     *
     * See `array_splice()` for more information.
     *
     * @param string|int $key
     * @return array The removed portion of the array.
     */
    public function arraySpliceAtKey(array & $array, $key, ?int $length = null, array $replacement = []): array
    {
        $keys   = array_keys($array);
        $offset = array_flip($keys)[$key] ?? null;
        if (is_null($offset))
        {
            throw new UnexpectedValueException("Array key not found: $key");
        }
        $values  = array_values($array);
        $_keys   = array_splice($keys, $offset, $length, array_keys($replacement));
        $_values = array_splice($values, $offset, $length, array_values($replacement));
        $array   = array_combine($keys, $values);

        return array_combine($_keys, $_values);
    }

    /**
     * Rename an array key without changing the order of values in the array
     *
     * @param string|int $key
     * @param string|int $newKey
     */
    public function renameArrayKey($key, $newKey, array $array): array
    {
        $this->arraySpliceAtKey($array, $key, 1, [$newKey => $array[$key] ?? null]);

        return $array;
    }

    /**
     * Convert an interval to the equivalent number of seconds
     *
     * Works with ISO 8601 durations like `PT48M`.
     *
     * @param DateInterval|string $value
     * @return int
     */
    public function intervalToSeconds($value): int
    {
        if (!($value instanceof DateInterval))
        {
            $value = new DateInterval($value);
        }
        $then = new DateTimeImmutable();
        $now  = $then->add($value);
        return $now->getTimestamp() - $then->getTimestamp();
    }

    /**
     * A shim for DateTimeImmutable::createFromInterface() (PHP 8+)
     *
     * @param DateTimeInterface $date
     * @return DateTimeImmutable
     */
    public function toDateTimeImmutable(DateTimeInterface $date): DateTimeImmutable
    {
        if ($date instanceof DateTimeImmutable)
        {
            return $date;
        }
        return DateTimeImmutable::createFromMutable($date);
    }

    /**
     * Convert a value to a DateTimeZone instance
     *
     * @param DateTimeZone|string $value
     * @return DateTimeZone
     */
    public function toTimezone($value): DateTimeZone
    {
        if ($value instanceof DateTimeZone)
        {
            return $value;
        }
        elseif (is_string($value))
        {
            return new DateTimeZone($value);
        }
        throw new UnexpectedValueException("Invalid timezone");
    }

    /**
     * If a value is 'falsey', make it null
     *
     * @param mixed $value
     * @return mixed Either `$value` or `null`.
     */
    public function emptyToNull($value)
    {
        return !$value ? null : $value;
    }

    /**
     * If an iterable isn't already an array, make it one
     *
     */
    public function iterableToArray(iterable $iterable, bool $preserveKeys = false): array
    {
        return is_array($iterable) ? $iterable : iterator_to_array($iterable, $preserveKeys);
    }

    /**
     * If an iterable isn't already an Iterator, enclose it in one
     *
     */
    public function iterableToIterator(iterable $iterable): Iterator
    {
        if ($iterable instanceof Iterator)
        {
            return $iterable;
        }

        return new IteratorIterator($iterable);
    }

    /**
     * Remove the directory and up to the given number of extensions from a path
     *
     * @param int $extLimit If set, remove extensions matching the regular
     * expression `\.[^.\s]+$` unless `""`, `"."`, or `".."` would remain:
     * - `<0`: remove all extensions
     * - `>0`: remove up to the given number of extensions
     */
    public function pathToBasename(string $path, int $extLimit = 0): string
    {
        $path = basename($path);
        if ($extLimit)
        {
            $range = $extLimit > 1 ? "{1,$extLimit}" : ($extLimit < 0 ? "+" : "");
            $path  = preg_replace("/(?<=.)(?<!^\\.|^\\.\\.)(\\.[^.\\s]+){$range}\$/", "", $path);
        }
        return $path;
    }

    /**
     * Remove the namespace and the first matched suffix from a class name
     *
     */
    public function classToBasename(string $class, string ...$suffixes): string
    {
        $class = substr(strrchr("\\" . $class, "\\"), 1);
        while (!is_null($suffix = array_shift($suffixes)))
        {
            if ($suffix && ($pos = strrpos($class, $suffix)) > 0)
            {
                return substr($class, 0, $pos);
            }
        }

        return $class;
    }

    /**
     * Return the namespace of a class
     *
     * Returns an empty string if `$class` is not namespaced, otherwise returns
     * the namespace without adding or removing the global prefix operator.
     *
     * @param string $class
     * @return string
     */
    public function classToNamespace(string $class): string
    {
        return substr($class, 0, max(0, strrpos("\\" . $class, "\\") - 1));
    }

    /**
     * Remove the class from a method name
     *
     * @param string $method
     * @return string
     */
    public function methodToFunction(string $method): string
    {
        return preg_replace('/^.*?([a-z0-9_]*)$/i', '$1', $method);
    }

    /**
     * Create a map from a list
     *
     * For example, to map from each array's `id` to the array itself:
     *
     * ```php
     * $list = [
     *     ['id' => 32, 'name' => 'Greta'],
     *     ['id' => 71, 'name' => 'Terry'],
     * ];
     *
     * $map = Convert::listToMap($list, 'id');
     *
     * print_r($map);
     * ```
     *
     * ```
     * Array
     * (
     *     [32] => Array
     *         (
     *             [id] => 32
     *             [name] => Greta
     *         )
     *
     *     [71] => Array
     *         (
     *             [id] => 71
     *             [name] => Terry
     *         )
     *
     * )
     * ```
     *
     * @param string|Closure $key Either the index or property name to use when
     * retrieving keys from arrays or objects in `$list`, or a closure that
     * returns a key for each item in `$list`.
     */
    public function listToMap(array $list, $key): array
    {
        return array_combine(
            array_map($this->_keyToClosure($key), $list),
            $list
        );
    }

    /**
     * Return the first item in $list where the value at $key is $value
     *
     * @param string|Closure $key Either the index or property name to use when
     * retrieving values from arrays or objects in `$list`, or a closure that
     * returns a value for each item in `$list`.
     * @return array|object|false `false` if no item was found in `$list` with
     * `$value` at `$key`.
     */
    public function iterableToItem(iterable $list, $key, $value, bool $strict = false)
    {
        $list    = $this->iterableToIterator($list);
        $closure = $this->_keyToClosure($key);

        while ($list->valid())
        {
            $item = $list->current();
            $list->next();
            if (($strict && ($closure($item) === $value)) ||
                (!$strict && ($closure($item) == $value)))
            {
                return $item;
            }
        }

        return false;
    }

    /**
     * @param string|Closure $key
     */
    private function _keyToClosure($key): Closure
    {
        if ($key instanceof Closure)
        {
            return $key;
        }

        return function ($item) use ($key)
        {
            if (is_array($item))
            {
                return $item[$key];
            }
            elseif (is_object($item))
            {
                return $item->$key;
            }
            else
            {
                throw new UnexpectedValueException("Item is not an array or object");
            }
        };
    }

    /**
     * Remove zero-width values from an array before imploding it
     *
     * @param string $separator
     * @param array $array
     * @return string
     */
    public function sparseToString(string $separator, array $array): string
    {
        return implode($separator, array_filter(
            $array,
            function ($value) { return strlen((string)$value) > 0; }
        ));
    }

    /**
     * Convert a scalar to a string
     *
     * @param mixed $value
     * @return string|false Returns `false` if `$value` is not a scalar
     */
    public function scalarToString($value)
    {
        if (is_scalar($value))
        {
            return (string)$value;
        }
        else
        {
            return false;
        }
    }

    /**
     * If $number is 1, return $singular, otherwise return $plural
     *
     * @param string|null $plural `"{$singular}s"` is used if `$plural` is
     * `null`.
     * @param bool $includeNumber If `true`, `"$number $noun"` is returned
     * instead of `"$noun"`.
     */
    public function plural(int $number, string $singular, ?string $plural = null, bool $includeNumber = false): string
    {
        $noun = ($number == 1
            ? $singular
            : (is_null($plural) ? $singular . "s" : $plural));

        return $includeNumber
            ? "$number $noun"
            : $noun;
    }

    /**
     * Return the plural of a singular noun
     *
     * @param string $noun
     * @return string
     */
    public function nounToPlural(string $noun): string
    {
        if (preg_match('/(?:(sh?|ch|x|z|(?<!^phot)(?<!^pian)(?<!^hal)o)|([^aeiou]y)|(is)|(on))$/i', $noun, $matches))
        {
            if ($matches[1])
            {
                return $noun . "es";
            }
            elseif ($matches[2])
            {
                return substr_replace($noun, "ies", -1);
            }
            elseif ($matches[3])
            {
                return substr_replace($noun, "es", -2);
            }
            elseif ($matches[4])
            {
                return substr_replace($noun, "a", -2);
            }
        }

        return $noun . "s";
    }

    /**
     * Convert a list of "key=value" strings to an array like ["key" => "value"]
     *
     * @param string[] $query
     * @return array<string,string>
     */
    public function queryToData(array $query): array
    {
        // 1. "key=value" to ["key", "value"]
        // 2. Discard "value", "=value", etc.
        // 3. ["key", "value"] => ["key" => "value"]
        return array_column(
            array_filter(
                array_map(
                    fn(string $kv) => explode("=", $kv, 2),
                    $query
                ),
                fn(array $kv) => count($kv) == 2 && trim($kv[0])
            ),
            1,
            0
        );
    }

    /**
     * Remove duplicates in a string where 'top-level' lines ("section names")
     * are grouped with any subsequent 'child' lines ("list items")
     *
     * Lines that match `$regex` are regarded as list items. Other lines are
     * used as the section name for subsequent list items. Blank lines clear the
     * current section name and are not included in the return value.
     *
     * @param string $text
     * @param string $separator Used between top-level lines and sections.
     * @param string|null $marker Added before each section name. The equivalent
     * number of spaces are added before each list item. To add a leading `"- "`
     * to top-level lines and indent others with two spaces, set `$marker` to
     * `"-"`.
     * @param string $regex
     * @return string
     */
    public function linesToLists(
        string $text,
        string $separator = "\n",
        ?string $marker   = null,
        string $regex     = '/^\h*[-*] /'
    ): string
    {
        $marker       = $marker ? $marker . " " : null;
        $indent       = $marker ? str_repeat(" ", mb_strlen($marker)) : "";
        $markerIsItem = $marker && preg_match($regex, $marker);

        $sections = [];
        foreach (preg_split('/\r\n|\n/', $text) as $line)
        {
            // Remove pre-existing markers early to ensure sections with the
            // same name are combined
            if ($marker && !$markerIsItem && strpos($line, $marker) === 0)
            {
                $line = substr($line, strlen($marker));
            }
            if (!trim($line))
            {
                unset($section);
                continue;
            }
            if (!preg_match($regex, $line))
            {
                $section = $line;
            }
            $key = $section ?? $line;
            if (!array_key_exists($key, $sections))
            {
                $sections[$key] = [];
            }
            if ($key != $line && !in_array($line, $sections[$key]))
            {
                $sections[$key][] = $line;
            }
        }
        // Move lines with no associated list to the top
        $sections = array_merge(
            array_filter($sections, fn($lines) => !count($lines)),
            array_filter($sections, fn($lines) => count($lines))
        );
        $groups = [];
        foreach ($sections as $section => $sectionLines)
        {
            if ($marker &&
                !($markerIsItem && strpos($section, $marker) === 0) &&
                !preg_match($regex, $section))
            {
                $section = $marker . $section;
            }
            $groups[] = $section;
            if ($sectionLines)
            {
                $groups[] = $indent . implode("\n" . $indent, $sectionLines);
            }
        }
        return implode($separator, $groups);
    }

    /**
     * Convert a 16-byte UUID to its 36-byte hexadecimal representation
     *
     */
    function uuidToHex(string $bytes): string
    {
        $uuid   = [];
        $uuid[] = substr($bytes, 0, 4);
        $uuid[] = substr($bytes, 4, 2);
        $uuid[] = substr($bytes, 6, 2);
        $uuid[] = substr($bytes, 8, 2);
        $uuid[] = substr($bytes, 10, 6);

        return implode("-", array_map(fn(string $bin): string => bin2hex($bin), $uuid));
    }

    /**
     * Convert php.ini values like "128M" to bytes
     *
     * @param string $size From the PHP FAQ: "The available options are K (for
     * Kilobytes), M (for Megabytes) and G (for Gigabytes), and are all
     * case-insensitive."
     * @return int
     */
    public function sizeToBytes(string $size): int
    {
        if (!preg_match('/^(.+?)([KMG]?)$/', strtoupper($size), $match) || !is_numeric($match[1]))
        {
            throw new UnexpectedValueException("Invalid shorthand: '$size'");
        }

        $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3];

        return (int)($match[1] * (1024 ** $power[$match[2]]));
    }

    /**
     * Convert the given strings and Stringables to an array of strings
     *
     * @param string|\Stringable ...$value
     * @return string[]
     */
    public function toStrings(...$value): array
    {
        return array_map(function ($string) { return (string)$string; }, $value);
    }

    /**
     * Perform the given case conversion
     *
     * @param string $text
     * @param int $case
     * @return string
     */
    public function toCase(string $text, int $case = self::IDENTIFIER_CASE_SNAKE): string
    {
        switch ($case)
        {
            case self::IDENTIFIER_CASE_SNAKE:

                return $this->toSnakeCase($text);

            case self::IDENTIFIER_CASE_KEBAB:

                return $this->toKebabCase($text);

            case self::IDENTIFIER_CASE_PASCAL:

                return $this->toPascalCase($text);

            case self::IDENTIFIER_CASE_CAMEL:

                return $this->toCamelCase($text);
        }

        throw new UnexpectedValueException("Invalid case: $case");
    }

    /**
     * Convert an identifier to snake_case
     *
     * @param string $text The identifier to convert.
     * @return string
     */
    public function toSnakeCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "_", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1_$2', $text);

        return strtolower(trim($text, "_"));
    }

    /**
     * Convert an identifier to kebab-case
     *
     * @param string $text
     * @return string
     */
    public function toKebabCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "-", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1-$2', $text);

        return strtolower(trim($text, "-"));
    }

    /**
     * Convert an identifier to PascalCase
     *
     * @param string $text
     * @return string
     */
    public function toPascalCase(string $text): string
    {
        $text = preg_replace_callback(
            '/([[:upper:]]?[[:lower:][:digit:]]+|([[:upper:]](?![[:lower:]]))+)/',
            function (array $matches) { return ucfirst(strtolower($matches[0])); },
            $text
        );

        return preg_replace("/[^[:alnum:]]+/", "", $text);
    }

    /**
     * Convert an identifier to camelCase
     *
     * @param string $text
     * @return string
     */
    public function toCamelCase(string $text): string
    {
        return lcfirst($this->toPascalCase($text));
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
     *
     * @param string $text
     * @return string
     */
    public function toNormal(string $text)
    {
        $replace = [
            "/(?<=[^&])&(?=[^&])/u" => " and ",
            "/\.+/u" => "",
            "/[^[:alnum:]]+/u" => " ",
        ];

        return strtoupper(trim(preg_replace(
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
     * @param object $object
     * @return array
     */
    public function objectToArray(object $object)
    {
        return get_object_vars($object);
    }

    private function _dataToQuery(array $data, bool $preserveKeys, DateFormatter $dateFormatter, ?string & $query = null, string $name = "", string $format = "%s"): string
    {
        if (is_null($query))
        {
            $query = "";
        }

        foreach ($data as $param => $value)
        {
            $_name = sprintf($format, $param);

            if (!is_array($value))
            {
                if (is_bool($value))
                {
                    $value = (int)$value;
                }
                elseif ($value instanceof DateTimeInterface)
                {
                    $value = $dateFormatter->format($value);
                }

                $query .= ($query ? "&" : "") . rawurlencode($name . $_name) . "=" . rawurlencode((string)$value);

                continue;
            }
            elseif (!$preserveKeys && Test::isListArray($value, true))
            {
                $_format = "[]";
            }
            else
            {
                $_format = "[%s]";
            }

            $this->_dataToQuery($value, $preserveKeys, $dateFormatter, $query, $name . $_name, $_format);
        }

        return $query;
    }

    /**
     * A more API-friendly http_build_query
     *
     * Booleans are cast to integers (`0` or `1`), {@see \DateTime}s are
     * formatted by `$dateFormatter`, and other values are cast to string.
     *
     * Arrays with consecutive integer keys numbered from 0 are considered to be
     * lists. By default, keys are not included when adding lists to query
     * strings. Set `$preserveKeys` to override this behaviour.
     *
     */
    public function dataToQuery(array $data, bool $preserveKeys = false, ?DateFormatter $dateFormatter = null): string
    {
        return $this->_dataToQuery(
            $data,
            $preserveKeys,
            $dateFormatter ?: new DateFormatter()
        );
    }

    public function valueToCode($value, string $delimiter = ", ", string $arrow = " => "): string
    {
        if (is_null($value))
        {
            return "null";
        }
        elseif (is_string($value) && preg_match('/\v/', $value))
        {
            return '"' . addcslashes($value, "\n\r\t\v\e\f\\\$\"") . '"';
        }
        elseif (is_array($value))
        {
            return $this->arrayToCode($value, $delimiter, $arrow);
        }
        return var_export($value, true);
    }

    public function arrayToCode(array $array, string $delimiter = ", ", string $arrow = " => "): string
    {
        if (empty($array))
        {
            return "[]";
        }
        $code = "";
        if (Test::isListArray($array))
        {
            foreach ($array as $value)
            {
                $code .= ($code ? $delimiter : "[")
                    . $this->valueToCode($value, $delimiter, $arrow);
            }
        }
        else
        {
            foreach ($array as $key => $value)
            {
                $code .= ($code ? $delimiter : "[")
                    . $this->valueToCode($key)
                    . $arrow
                    . $this->valueToCode($value, $delimiter, $arrow);
            }
        }
        return $code . "]";
    }

    /**
     * @deprecated Use {@see Conversions::plural()} instead
     */
    public function numberToNoun(int $number, string $singular, ?string $plural = null, bool $includeNumber = false): string
    {
        return $this->plural($number, $singular, $plural, $includeNumber);
    }

}
