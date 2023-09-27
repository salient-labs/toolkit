<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Support\Iterator\Contract\MutableIterator;
use Lkrms\Support\Iterator\RecursiveObjectOrArrayIterator;
use Lkrms\Support\DateFormatter;
use Lkrms\Utility\Test;
use ArrayIterator;
use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Iterator;
use IteratorIterator;
use LogicException;
use RecursiveIteratorIterator;

/**
 * Convert data from one type/format/structure to another
 *
 * Examples:
 * - normalise alphanumeric text
 * - convert a list array to a map array
 * - pluralise a singular noun
 * - extract a class name from a FQCN
 */
final class Convert
{
    /**
     * Cast a value to a boolean, preserving null and converting boolean strings
     *
     * @see Test::isBoolValue()
     */
    public static function toBoolOrNull($value): ?bool
    {
        return is_null($value)
            ? null
            : (is_string($value) && Pcre::match('/^' . Regex::BOOLEAN_STRING . '$/', $value, $match)
                ? ($match['true'] ? true : false)
                : (bool) $value);
    }

    /**
     * Cast a value to an integer, preserving null
     */
    public static function toIntOrNull($value): ?int
    {
        return is_null($value) ? null : (int) $value;
    }

    /**
     * If a value isn't an array, make it the first element of one
     *
     * Cast `$value` to `array` instead of calling this method unless you need a
     * non-empty array (`[null]` instead of `[]`) when `$value` is `null`.
     *
     * @return array Either `$value`, `[$value]`, or `[]` (only if
     * `$emptyIfNull` is set and `$value` is `null`).
     */
    public static function toArray($value, bool $emptyIfNull = false): array
    {
        return is_array($value)
            ? $value
            : ($emptyIfNull && is_null($value) ? [] : [$value]);
    }

    /**
     * If a value isn't a list, make it the first element of one
     *
     * @return array Either `$value`, `[$value]`, or `[]` (only if
     * `$emptyIfNull` is set and `$value` is `null`).
     */
    public static function toList($value, bool $emptyIfNull = false): array
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
    public static function flatten($value)
    {
        if (!is_array($value) || count($value) !== 1 || array_key_first($value) !== 0) {
            return $value;
        }

        return self::flatten(reset($value));
    }

    /**
     * array_walk_recursive for arbitrarily nested objects and arrays
     *
     * @param object|mixed[] $objectOrArray
     * @param callable(mixed, array-key, MutableIterator<array-key,mixed>&\RecursiveIterator<array-key,mixed>): bool $callback Return `false` to stop iterating over `$objectOrArray`.
     */
    public static function walkRecursive(
        &$objectOrArray,
        callable $callback,
        int $mode = RecursiveIteratorIterator::LEAVES_ONLY
    ): void {
        $iterator = new RecursiveObjectOrArrayIterator($objectOrArray);
        $iterator = new RecursiveIteratorIterator($iterator, $mode);
        foreach ($iterator as $key => $value) {
            if (!$callback($value, $key, $iterator->getSubIterator())) {
                return;
            }
        }
    }

    /**
     * A type-agnostic array_unique
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @return array<TKey,TValue>
     */
    public static function toUnique(array $array): array
    {
        $list = [];
        foreach ($array as $key => $value) {
            if (in_array($value, $list, true)) {
                continue;
            }
            $list[$key] = $value;
        }

        return $list;
    }

    /**
     * A type-agnostic array_unique with reindexing
     *
     * @template T
     * @param T[] $array
     * @return T[]
     */
    public static function toUniqueList(array $array): array
    {
        $list = [];
        foreach ($array as $value) {
            if (in_array($value, $list, true)) {
                continue;
            }
            $list[] = $value;
        }

        return $list;
    }

    /**
     * A type-agnostic multi-column array_unique
     *
     * It is assumed that every array provided has the same signature (i.e.
     * identical lengths and keys).
     *
     * Whenever a value is excluded from `$array`, its counterparts in the
     * `$columns` arrays are also excluded. Only values in `$array` are checked
     * for uniqueness.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey,TValue> $array
     * @param array<TKey,mixed> ...$columns
     * @return array<TKey,TValue>
     */
    public static function columnsToUnique(array $array, array &...$columns): array
    {
        $list = [];
        foreach ($array as $key => $value) {
            if (in_array($value, $list, true)) {
                continue;
            }
            $list[$key] = $value;
            foreach ($columns as $columnIndex => $column) {
                $columns2[$columnIndex][$key] = $column[$key];
            }
        }
        foreach ($columns as $columnIndex => &$column) {
            $column = $columns2[$columnIndex] ?? [];
        }

        return $list;
    }

    /**
     * A type-agnostic multi-column array_unique with reindexing
     *
     * It is assumed that every array provided has the same signature (i.e.
     * identical lengths and keys).
     *
     * Whenever a value is excluded from `$array`, its counterparts in the
     * `$columns` arrays are also excluded. Only values in `$array` are checked
     * for uniqueness.
     *
     * @template T
     * @param T[] $array
     * @param mixed[] ...$columns
     * @return T[]
     */
    public static function columnsToUniqueList(array $array, array &...$columns): array
    {
        $list = [];
        foreach ($array as $rowIndex => $value) {
            if (in_array($value, $list, true)) {
                continue;
            }
            $list[] = $value;
            foreach ($columns as $columnIndex => $column) {
                $columns2[$columnIndex][] = $column[$rowIndex];
            }
        }
        foreach ($columns as $columnIndex => &$column) {
            $column = $columns2[$columnIndex] ?? [];
        }

        return $list;
    }

    /**
     * A faster array_unique
     *
     * @template TKey of array-key
     * @param array<TKey,string> $array
     * @return array<TKey,string>
     */
    public static function stringsToUnique(array $array): array
    {
        $list = [];
        $seen = [];
        foreach ($array as $key => $value) {
            if (isset($seen[$value])) {
                continue;
            }
            $list[$key] = $value;
            $seen[$value] = true;
        }

        return $list;
    }

    /**
     * A faster array_unique with reindexing
     *
     * @param string[] $array
     * @return string[]
     */
    public static function stringsToUniqueList(array $array): array
    {
        $list = [];
        $seen = [];
        foreach ($array as $value) {
            if (isset($seen[$value])) {
                continue;
            }
            $list[] = $value;
            $seen[$value] = true;
        }

        return $list;
    }

    /**
     * JSON-encode non-scalar values in an array
     *
     * @return array<int,int|float|string|bool|null>
     */
    public static function toScalarArray(array $array): array
    {
        foreach ($array as &$value) {
            if (is_scalar($value) || is_null($value)) {
                continue;
            }
            $value = json_encode($value);
        }

        return $array;
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
        // 3. Reindex
        return array_values(
            // 2. Trim each substring, remove empty strings
            Arr::trimAndCompact(
                // 1. Split the string
                explode($separator, $string),
                $characters
            )
        );
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
        return Arr::trimAndCompact(
            self::splitOutsideBrackets($separator, $string),
            $characters
        );
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
            throw new LogicException('Argument #1 ($separator) must be a single character');
        }
        if (strpos('()<>[]{}', $separator) !== false) {
            throw new LogicException('Argument #1 ($separator) cannot be a bracket character');
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
            $matches
        );
        return $matches[0];
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
        $eol = Inspect::getEol($text) ?: "\n";
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
        $eol = Inspect::getEol($text) ?: "\n";
        $softTab = str_repeat(' ', $tabSize);
        $expanded = '';
        foreach (explode($eol, $text) as $i => $line) {
            !$i || $expanded .= $eol;
            if ($i || (!$preserveLine1 && $column === 1)) {
                $expanded .= Pcre::replace('/(?<=\n|\G)\t/', $softTab, $line);
                continue;
            }
            if (!$i && $preserveLine1) {
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
     * Get the offset of a key in an array
     *
     * @param string|int $key
     * @return int|null `null` if `$key` is not found in `$array`.
     */
    public static function arrayKeyToOffset($key, array $array): ?int
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
    public static function arraySpliceAtKey(array &$array, $key, ?int $length = null, array $replacement = []): array
    {
        $keys = array_keys($array);
        $offset = array_flip($keys)[$key] ?? null;
        if (is_null($offset)) {
            throw new LogicException("Array key not found: $key");
        }
        // $length can't be null in PHP 7.4
        if (is_null($length)) {
            $length = count($array);
        }
        $values = array_values($array);
        $_keys = array_splice($keys, $offset, $length, array_keys($replacement));
        $_values = array_splice($values, $offset, $length, array_values($replacement));
        $array = array_combine($keys, $values);

        return array_combine($_keys, $_values);
    }

    /**
     * Rename an array key without changing the order of values in the array
     *
     * @param string|int $key
     * @param string|int $newKey
     */
    public static function renameArrayKey($key, $newKey, array $array): array
    {
        self::arraySpliceAtKey($array, $key, 1, [$newKey => $array[$key] ?? null]);

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
     * A shim for DateTimeImmutable::createFromInterface() (PHP 8+)
     */
    public static function toDateTimeImmutable(DateTimeInterface $date): DateTimeImmutable
    {
        return $date instanceof DateTimeImmutable
            ? $date
            : DateTimeImmutable::createFromMutable($date);
    }

    /**
     * Convert a value to a DateTimeZone instance
     *
     * @param DateTimeZone|string $value
     * @return DateTimeZone
     */
    public static function toTimezone($value): DateTimeZone
    {
        if ($value instanceof DateTimeZone) {
            return $value;
        } elseif (is_string($value)) {
            return new DateTimeZone($value);
        }
    }

    /**
     * If a value is 'falsey', make it null
     *
     * @return mixed Either `$value` or `null`.
     */
    public static function emptyToNull($value)
    {
        return !$value ? null : $value;
    }

    /**
     * Get the first value that is not null
     */
    public static function coalesce(...$values)
    {
        while ($values) {
            if (!is_null($value = array_shift($values))) {
                return $value;
            }
        }

        return null;
    }

    /**
     * If an iterable isn't already an array, make it one
     */
    public static function iterableToArray(iterable $iterable, bool $preserveKeys = false): array
    {
        return is_array($iterable) ? $iterable : iterator_to_array($iterable, $preserveKeys);
    }

    /**
     * If an iterable isn't already an Iterator, enclose it in one
     */
    public static function iterableToIterator(iterable $iterable): Iterator
    {
        if ($iterable instanceof Iterator) {
            return $iterable;
        }
        if (is_array($iterable)) {
            return new ArrayIterator($iterable);
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
     * Resolve relative segments in a pathname
     *
     * e.g. `/dir/subdir/files/../../subdir2/./doc` becomes `/dir/subdir2/doc`.
     *
     * @see Conversions::resolveRelativeUrl()
     */
    public static function resolvePath(string $path): string
    {
        $path = Pcre::replace(['@(?<=/)\./@', '@(?<=/)\.$@'], '', $path);
        do {
            $path = Pcre::replace('@(?<=/)(?!\.\./)[^/]+/\.\./@', '', $path, 1, $count);
        } while ($count);
        $path = Pcre::replace('@(?<=/)(?!\.\./)[^/]+/\.\.$@', '', $path, 1, $count);

        return rtrim($path, '/');
    }

    /**
     * Get the absolute form of a URL relative to a base URL, as per [RFC1808]
     */
    public static function resolveRelativeUrl(string $embeddedUrl, string $baseUrl): string
    {
        // Step 1
        if (!$baseUrl) {
            return $embeddedUrl;
        }
        // Step 2a
        if (!$embeddedUrl) {
            return $baseUrl;
        }
        $url = self::parseUrl($embeddedUrl);
        // Step 2b
        if (isset($url['scheme'])) {
            return $embeddedUrl;
        }
        $base = self::parseUrl($baseUrl);
        // Step 2c
        $url['scheme'] = $base['scheme'] ?? null;
        // Step 3
        if (self::netLoc($url)) {
            return self::unparseUrl($url);
        }
        $url = self::netLoc($base) + $url;
        // Step 4
        if (substr($path = $url['path'] ?? '', 0, 1) === '/') {
            return self::unparseUrl($url);
        }
        // Step 5
        if (!$path) {
            $url['path'] = $base['path'] ?? null;
            // Step 5a
            if (!($url['params'] ?? null)) {
                $url['params'] = $base['params'] ?? null;
                // Step 5b
                if (!($url['query'] ?? null)) {
                    $url['query'] = $base['query'] ?? null;
                }
            }

            return self::unparseUrl($url);
        }
        $base['path'] = $base['path'] ?? '';
        // Step 6
        $path = substr($base['path'], 0, strrpos("/{$base['path']}", '/')) . $path;
        // Steps 6a and 6b
        $path = Pcre::replace(['@(?<=/)\./@', '@(?<=/)\.$@'], '', $path);
        // Step 6c
        do {
            $path = Pcre::replace('@(?<=/)(?!\.\./)[^/]+/\.\./@', '', $path, 1, $count);
        } while ($count);
        // Step 6d
        $url['path'] = Pcre::replace('@(?<=/)(?!\.\./)[^/]+/\.\.$@', '', $path, 1, $count);

        return self::unparseUrl($url);
    }

    private static function netLoc(array $url): array
    {
        return array_intersect_key($url, array_flip(['host', 'port', 'user', 'pass']));
    }

    /**
     * Parse a URL and return its components, including "params" if FTP
     * parameters are present
     *
     * Other components are as per `parse_url`.
     *
     * @return array|false `false` if `$url` cannot be parsed.
     */
    public static function parseUrl(string $url)
    {
        // Extract "params" early because parse_url doesn't accept URLs where
        // "path" has a leading ";"
        if (strpos($url, ';') !== false) {
            Pcre::match('/;([^?]*)/', $url, $matches);
            $params = $matches[1];
            $url = Pcre::replace('/;[^?]*/', '', $url, 1);
        }
        if (($url = parse_url($url)) === false) {
            return false;
        }
        if (isset($params)) {
            $url['params'] = $params;
        }

        return $url;
    }

    /**
     * Convert a parse_url array to a string
     *
     * Arrays returned by {@see Conversions::parseUrl()} are also converted.
     *
     * @param array<string,string|int> $url
     */
    public static function unparseUrl(array $url): string
    {
        [$u, $url] = [$url, ''];
        !($u['scheme'] ?? null) || $url .= "{$u['scheme']}:";
        if (isset($u['host'])) {
            $url .= '//';
            !array_key_exists('user', $u) || $auth = $u['user'];
            !array_key_exists('pass', $u) || $auth = ($auth ?? '') . ":{$u['pass']}";
            is_null($auth ?? null) || $url .= "$auth@";
            $url .= $u['host'];
            !array_key_exists('port', $u) || $url .= ":{$u['port']}";
        }
        !($u['path'] ?? null) || $url .= $u['path'];
        !array_key_exists('params', $u) || $url .= ";{$u['params']}";
        !array_key_exists('query', $u) || $url .= "?{$u['query']}";
        !array_key_exists('fragment', $u) || $url .= "#{$u['fragment']}";

        return $url;
    }

    /**
     * Remove the namespace and the first matched suffix from a class name
     */
    public static function classToBasename(string $class, string ...$suffixes): string
    {
        $class = substr(strrchr('\\' . $class, '\\'), 1);
        while ($suffixes) {
            if (($suffix = array_shift($suffixes)) && ($pos = strrpos($class, $suffix)) > 0) {
                return substr($class, 0, $pos);
            }
        }

        return $class;
    }

    /**
     * Get the namespace of a class
     *
     * Returns an empty string if `$class` is not namespaced, otherwise returns
     * the namespace without adding or removing the global prefix operator.
     */
    public static function classToNamespace(string $class): string
    {
        return substr($class, 0, max(0, strrpos('\\' . $class, '\\') - 1));
    }

    /**
     * Remove the class from a method name
     */
    public static function methodToFunction(string $method): string
    {
        return Pcre::replace('/^.*?([a-z0-9_]*)$/i', '$1', $method);
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
     * @param int|string|Closure $key Either the index or property name to use
     * when retrieving keys from arrays or objects in `$list`, or a closure that
     * returns a key for each item in `$list`.
     */
    public static function listToMap(array $list, $key): array
    {
        return array_combine(
            array_map(self::_keyToClosure($key), $list),
            $list
        );
    }

    /**
     * Get the first item in $list where the value at $key is $value
     *
     * @param int|string|Closure $key Either the index or property name to use
     * when retrieving values from arrays or objects in `$list`, or a closure
     * that returns a value for each item in `$list`.
     * @return array|object|false `false` if no item was found in `$list` with
     * `$value` at `$key`.
     */
    public static function iterableToItem(iterable $list, $key, $value, bool $strict = false)
    {
        $list = self::iterableToIterator($list);
        $closure = self::_keyToClosure($key);

        while ($list->valid()) {
            $item = $list->current();
            $list->next();
            if (($strict && ($closure($item) === $value)) ||
                    (!$strict && ($closure($item) == $value))) {
                return $item;
            }
        }

        return false;
    }

    /**
     * @param int|string|Closure $key
     */
    private static function _keyToClosure($key): Closure
    {
        return $key instanceof Closure
            ? $key
            : fn($item) => self::valueAtKey($item, $key);
    }

    /**
     * Get the value at $key in $item, where $item is an array or object
     *
     * @param array|\ArrayAccess|object $item
     * @param int|string $key
     * @return mixed
     */
    public static function valueAtKey($item, $key)
    {
        return is_array($item) || $item instanceof \ArrayAccess
            ? $item[$key]
            : $item->$key;
    }

    /**
     * Remove zero-width values from an array before imploding it
     */
    public static function sparseToString(string $separator, array $array): string
    {
        return implode($separator, array_filter(
            $array,
            function ($value) { return strlen((string) $value) > 0; }
        ));
    }

    /**
     * Convert a scalar to a string
     *
     * @return string|false Returns `false` if `$value` is not a scalar
     */
    public static function scalarToString($value)
    {
        if (is_scalar($value)) {
            return (string) $value;
        } else {
            return false;
        }
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
            : (is_null($plural) ? $singular . 's' : $plural);

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
            ? sprintf('between %s %d and %d', is_null($plural) ? $singular . 's' : $plural, $from, $to)
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
     * Remove duplicates in a string where 'top-level' lines ("section names")
     * are grouped with any subsequent 'child' lines ("list items")
     *
     * Lines that match `$regex` are regarded as list items. Other lines are
     * used as the section name for subsequent list items. Blank lines between
     * list items clear the current section name.
     *
     * If a named subpattern in `$regex` called `indent` matches a non-empty
     * string, subsequent lines with the same number of spaces for indentation
     * as there are characters in the match are treated as part of the item,
     * including any blank lines.
     *
     * @param string $separator Used between top-level lines and sections.
     * @param string|null $marker Added before each section name. The equivalent
     * number of spaces are added before each list item. To add a leading `"- "`
     * to top-level lines and indent others with two spaces, set `$marker` to
     * `"-"`.
     * @param bool $clean If `true`, the first match of `$regex` in each section
     * name is removed.
     */
    public static function linesToLists(
        string $text,
        string $separator = "\n",
        ?string $marker = null,
        string $regex = '/^(?P<indent>\h*[-*] )/',
        bool $clean = false
    ): string {
        $marker = ($marker ?? '') !== '' ? $marker . ' ' : null;
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
                if ($lastWasItem) {
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
                    };
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
        $escapes = $ignoreEscapes ? '' : Regex::NOT_ESCAPED . '\K';

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
     * @return int
     */
    public static function sizeToBytes(string $size): int
    {
        if (!Pcre::match('/^(.+?)([KMG]?)$/', strtoupper($size), $match) || !is_numeric($match[1])) {
            throw new LogicException("Invalid shorthand: '$size'");
        }

        $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3];

        return (int) ($match[1] * (1024 ** $power[$match[2]]));
    }

    /**
     * Convert the given strings and Stringables to an array of strings
     *
     * @param string|\Stringable ...$value
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
     * Convert an identifier to snake_case
     */
    public static function toSnakeCase(string $text, ?string $preserve = null): string
    {
        return strtolower(self::splitWords($text, $preserve, '_'));
    }

    /**
     * Convert an identifier to kebab-case
     */
    public static function toKebabCase(string $text, ?string $preserve = null): string
    {
        return strtolower(self::splitWords($text, $preserve, '-'));
    }

    /**
     * Convert an identifier to PascalCase
     */
    public static function toPascalCase(string $text, ?string $preserve = null): string
    {
        return self::splitWords(
            $text, $preserve, '', fn(string $word): string => ucfirst(strtolower($word))
        );
    }

    /**
     * Convert an identifier to camelCase
     */
    public static function toCamelCase(string $text, ?string $preserve = null): string
    {
        return lcfirst(self::toPascalCase($text, $preserve));
    }

    /**
     * Optionally delimit and apply a callback to words in a string before
     * removing any non-alphanumeric characters
     *
     * @param (callable(string): string)|null $callback
     */
    public static function splitWords(string $text, ?string $preserve, string $delimiter, ?callable $callback = null): string
    {
        $regex = '(?:[[:upper:]]?[[:lower:][:digit:]]+|(?:[[:upper:]](?![[:lower:]]))+[[:digit:]]*)';
        if (($preserve ?? '') === '') {
            $preserve = '';
            $delimitRegex = '';
        } else {
            $preserve = addcslashes($preserve, '-/[\^');
            $delimitRegex = "(?<![[:alnum:]{$preserve}])";
        }

        // Add a delimiter before words not adjacent to a preserved character
        if ($delimiter !== '') {
            $text = Pcre::replace(
                "/$delimitRegex$regex/u", $delimiter . '$0', $text
            );
        }

        // Apply a callback to every word
        if ($callback) {
            $text = Pcre::replaceCallback(
                "/$regex/u", fn(array $match): string => $callback($match[0]), $text
            );
        }

        // Replace one or more non-alphanumeric characters with one delimiter
        $text = Pcre::replace("/[^[:alnum:]$preserve]+/", $delimiter, $text);

        // Remove leading and trailing delimiters
        return
            $delimiter === ''
                ? $text
                : trim($text, $delimiter);
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

        return strtoupper(trim(Pcre::replace(
            array_keys($replace),
            array_values($replace),
            $text
        )));
    }

    /**
     * Replace a string's CRLF or CR end-of-line sequences with LF
     *
     * @deprecated Use {@see Str::setEol()} instead
     */
    public static function lineEndingsToUnix(string $string): string
    {
        if (strpos($string, "\r") === false) {
            return $string;
        }

        return Pcre::replace("/(\r\n|\r)/", "\n", $string);
    }

    /**
     * A wrapper for get_object_vars
     *
     * Because you can't exclude `private` and `protected` properties from
     * inside the class. (Not easily, anyway.)
     */
    public static function objectToArray(object $object): array
    {
        return get_object_vars($object);
    }

    private static function _dataToQuery(
        array $data,
        bool $preserveKeys,
        DateFormatter $dateFormatter,
        ?string &$query = null,
        string $name = '',
        string $format = '%s'
    ): string {
        if (is_null($query)) {
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
            } elseif (!$preserveKeys && Test::isListArray($value, true)) {
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
     */
    public static function dataToQuery(array $data, bool $preserveKeys = false, ?DateFormatter $dateFormatter = null): string
    {
        return self::_dataToQuery(
            $data,
            $preserveKeys,
            $dateFormatter ?: new DateFormatter()
        );
    }

    /**
     * Like var_export but with more compact output
     */
    public static function valueToCode(
        $value,
        string $delimiter = ', ',
        string $arrow = ' => ',
        ?string $escapeCharacters = null
    ): string {
        if (is_null($value)) {
            return 'null';
        }
        if (is_string($value) &&
            (($escapeCharacters && strpbrk($value, $escapeCharacters) !== false) ||
                Pcre::match('/\v/', $value))) {
            $escaped = addcslashes($value, "\n\r\t\v\e\f\\\$\"" . $escapeCharacters);
            if ($escapeCharacters) {
                foreach (str_split($escapeCharacters) as $character) {
                    $oct = sprintf('\%03o', ord($character));
                    $escaped = str_replace(addcslashes($character, $character), $oct, $escaped);
                }
            }

            return '"' . $escaped . '"';
        }
        if (!is_array($value)) {
            return var_export($value, true);
        }
        if (empty($value)) {
            return '[]';
        }
        $code = '';
        if (Test::isListArray($value)) {
            foreach ($value as $value) {
                $code .= ($code ? $delimiter : '[')
                    . self::valueToCode($value, $delimiter, $arrow, $escapeCharacters);
            }
        } else {
            foreach ($value as $key => $value) {
                $code .= ($code ? $delimiter : '[')
                    . self::valueToCode($key, $delimiter, $arrow, $escapeCharacters)
                    . $arrow
                    . self::valueToCode($value, $delimiter, $arrow, $escapeCharacters);
            }
        }

        return $code . ']';
    }
}
