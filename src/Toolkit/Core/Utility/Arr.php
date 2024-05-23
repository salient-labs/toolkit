<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Contract\Core\Jsonable;
use Salient\Core\AbstractUtility;
use ArrayAccess;
use OutOfRangeException;
use Stringable;

/**
 * Work with arrays
 *
 * @api
 */
final class Arr extends AbstractUtility
{
    public const SORT_REGULAR = \SORT_REGULAR;
    public const SORT_NUMERIC = \SORT_NUMERIC;
    public const SORT_STRING = \SORT_STRING;
    public const SORT_LOCALE_STRING = \SORT_LOCALE_STRING;
    public const SORT_NATURAL = \SORT_NATURAL;
    public const SORT_FLAG_CASE = \SORT_FLAG_CASE;

    /**
     * Get a value from nested arrays using dot notation
     *
     * @param mixed[] $array
     * @param mixed $default
     * @return mixed
     * @throws OutOfRangeException if `$key` is not found in `$array` and no
     * `$default` is given.
     */
    public static function get(string $key, array $array, $default = null)
    {
        foreach (explode('.', $key) as $part) {
            if (is_array($array) && array_key_exists($part, $array)) {
                $array = $array[$part];
                continue;
            }
            if (func_num_args() < 3) {
                throw new OutOfRangeException(sprintf(
                    'Array key not found: %s',
                    $key,
                ));
            }
            return $default;
        }
        return $array;
    }

    /**
     * Check if a value exists in nested arrays using dot notation
     *
     * @param mixed[] $array
     */
    public static function has(string $key, array $array): bool
    {
        foreach (explode('.', $key) as $part) {
            if (!is_array($array) || !array_key_exists($part, $array)) {
                return false;
            }
            $array = $array[$part];
        }
        return true;
    }

    /**
     * Get the first value in an array
     *
     * @template TValue
     *
     * @param array<array-key,TValue> $array
     * @return ($array is non-empty-array ? TValue : null)
     */
    public static function first(array $array)
    {
        if ($array === []) {
            return null;
        }
        return reset($array);
    }

    /**
     * Get the last value in an array
     *
     * @template TValue
     *
     * @param array<array-key,TValue> $array
     * @return ($array is non-empty-array ? TValue : null)
     */
    public static function last(array $array)
    {
        if ($array === []) {
            return null;
        }
        return end($array);
    }

    /**
     * Get the key of a value in an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param TValue $value
     * @param array<TKey,TValue> $array
     * @return TKey
     * @throws OutOfRangeException if `$value` is not found in `$array`.
     */
    public static function keyOf($value, array $array)
    {
        $key = array_search($value, $array, true);
        if ($key === false) {
            throw new OutOfRangeException('Value not found in array');
        }
        /** @var TKey */
        return $key;
    }

    /**
     * Shift an element off the beginning of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TValue|null $shifted
     * @param-out ($array is non-empty-array ? TValue : null) $shifted
     * @return array<TKey,TValue>
     */
    public static function shift(array $array, &$shifted = null): array
    {
        $shifted = array_shift($array);
        return $array;
    }

    /**
     * Add elements to the beginning of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TValue ...$values
     * @return array<TKey,TValue>
     */
    public static function unshift(array $array, ...$values): array
    {
        array_unshift($array, ...$values);
        return $array;
    }

    /**
     * Pop a value off the end of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TValue|null $popped
     * @param-out ($array is non-empty-array ? TValue : null) $popped
     * @return array<TKey,TValue>
     */
    public static function pop(array $array, &$popped = null): array
    {
        $popped = array_pop($array);
        return $array;
    }

    /**
     * Push values onto the end of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TValue ...$values
     * @return array<TKey,TValue>
     */
    public static function push(array $array, ...$values): array
    {
        array_push($array, ...$values);
        return $array;
    }

    /**
     * Push values onto the end of an array after excluding any that are already
     * present
     *
     * @template TKey of array-key
     * @template TValue
     * @template T
     *
     * @param array<TKey,TValue> $array
     * @param T ...$values
     * @return array<TKey|int,TValue|T>
     */
    public static function extend(array $array, ...$values): array
    {
        return array_merge($array, array_diff($values, $array));
    }

    /**
     * Assign a value to an element of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TKey $key
     * @param TValue $value
     * @return array<TKey,TValue>
     */
    public static function set(array $array, $key, $value): array
    {
        $array[$key] = $value;
        return $array;
    }

    /**
     * Assign a value to an element of an array if it isn't already set
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TKey $key
     * @param TValue $value
     * @return array<TKey,TValue>
     */
    public static function setIf(array $array, $key, $value): array
    {
        if (!array_key_exists($key, $array)) {
            $array[$key] = $value;
        }
        return $array;
    }

    /**
     * Remove a key from an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TKey $key
     * @return array<TKey,TValue>
     */
    public static function unset(array $array, $key): array
    {
        unset($array[$key]);
        return $array;
    }

    /**
     * Sort an array by value
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param (callable(TValue, TValue): int)|int-mask-of<Arr::SORT_*> $callbackOrFlags
     * @return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public static function sort(
        array $array,
        bool $preserveKeys = false,
        $callbackOrFlags = \SORT_REGULAR
    ): array {
        if (is_callable($callbackOrFlags)) {
            if ($preserveKeys) {
                uasort($array, $callbackOrFlags);
                return $array;
            }
            usort($array, $callbackOrFlags);
            return $array;
        }
        if ($preserveKeys) {
            asort($array, $callbackOrFlags);
            return $array;
        }
        sort($array, $callbackOrFlags);
        return $array;
    }

    /**
     * Sort an array by value in descending order
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param int-mask-of<Arr::SORT_*> $flags
     * @return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public static function sortDesc(
        array $array,
        bool $preserveKeys = false,
        int $flags = \SORT_REGULAR
    ): array {
        if ($preserveKeys) {
            arsort($array, $flags);
            return $array;
        }
        rsort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by key
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param (callable(TKey, TKey): int)|int-mask-of<Arr::SORT_*> $callbackOrFlags
     * @return array<TKey,TValue>
     */
    public static function sortByKey(
        array $array,
        $callbackOrFlags = \SORT_REGULAR
    ): array {
        if (is_callable($callbackOrFlags)) {
            uksort($array, $callbackOrFlags);
            return $array;
        }
        ksort($array, $callbackOrFlags);
        return $array;
    }

    /**
     * Sort an array by key in descending order
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param int-mask-of<Arr::SORT_*> $flags
     * @return array<TKey,TValue>
     */
    public static function sortByKeyDesc(
        array $array,
        int $flags = \SORT_REGULAR
    ): array {
        krsort($array, $flags);
        return $array;
    }

    /**
     * Remove duplicate values from an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey,TValue> $array
     * @return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public static function unique(
        iterable $array,
        bool $preserveKeys = false
    ): array {
        $unique = [];
        foreach ($array as $key => $value) {
            if (in_array($value, $unique, true)) {
                continue;
            }
            if ($preserveKeys) {
                $unique[$key] = $value;
            } else {
                $unique[] = $value;
            }
        }
        return $unique;
    }

    /**
     * Check if a value is an array with integer keys numbered consecutively
     * from 0
     *
     * @param mixed $value
     * @phpstan-assert-if-true list<mixed> $value
     */
    public static function isList($value, bool $orEmpty = false): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $orEmpty;
        }
        $i = 0;
        foreach ($value as $key => $value) {
            if ($i++ !== $key) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a value is an array with integer keys
     *
     * @param mixed $value
     * @phpstan-assert-if-true array<int,mixed> $value
     */
    public static function isIndexed($value, bool $orEmpty = false): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $orEmpty;
        }
        foreach ($value as $key => $value) {
            if (!is_int($key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if a value is an array of integers or an array of strings
     *
     * @param mixed $value
     * @phpstan-assert-if-true int[]|string[] $value
     */
    public static function ofArrayKey($value, bool $orEmpty = false): bool
    {
        return self::ofInt($value, $orEmpty) || self::ofString($value);
    }

    /**
     * Check if a value is a list of integers or a list of strings
     *
     * @param mixed $value
     * @phpstan-assert-if-true list<int>|list<string> $value
     */
    public static function isListOfArrayKey($value, bool $orEmpty = false): bool
    {
        return self::isListOfInt($value, $orEmpty) || self::isListOfString($value);
    }

    /**
     * Check if a value is an array of integers
     *
     * @param mixed $value
     * @phpstan-assert-if-true int[] $value
     */
    public static function ofInt($value, bool $orEmpty = false): bool
    {
        return self::doIsArrayOf('is_int', $value, $orEmpty, false);
    }

    /**
     * Check if a value is a list of integers
     *
     * @param mixed $value
     * @phpstan-assert-if-true list<int> $value
     */
    public static function isListOfInt($value, bool $orEmpty = false): bool
    {
        return self::doIsArrayOf('is_int', $value, $orEmpty, true);
    }

    /**
     * Check if a value is an array of strings
     *
     * @param mixed $value
     * @phpstan-assert-if-true string[] $value
     */
    public static function ofString($value, bool $orEmpty = false): bool
    {
        return self::doIsArrayOf('is_string', $value, $orEmpty, false);
    }

    /**
     * Check if a value is a list of strings
     *
     * @param mixed $value
     * @phpstan-assert-if-true list<string> $value
     */
    public static function isListOfString($value, bool $orEmpty = false): bool
    {
        return self::doIsArrayOf('is_string', $value, $orEmpty, true);
    }

    /**
     * Check if a value is an array of instances of a given class
     *
     * @template T of object
     *
     * @param mixed $value
     * @param class-string<T> $class
     * @phpstan-assert-if-true T[] $value
     */
    public static function of($value, string $class, bool $orEmpty = false): bool
    {
        return self::doIsArrayOf('is_a', $value, $orEmpty, false, $class);
    }

    /**
     * Check if a value is a list of instances of a given class
     *
     * @template T of object
     *
     * @param mixed $value
     * @param class-string<T> $class
     * @phpstan-assert-if-true list<T> $value
     */
    public static function isListOf($value, string $class, bool $orEmpty = false): bool
    {
        return self::doIsArrayOf('is_a', $value, $orEmpty, true, $class);
    }

    /**
     * @param string&callable $func
     * @param mixed $value
     * @param mixed ...$args
     */
    private static function doIsArrayOf(string $func, $value, bool $orEmpty, bool $requireList, ...$args): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if ($value === []) {
            return $orEmpty;
        }
        $i = 0;
        foreach ($value as $key => $value) {
            if ($requireList && $i++ !== $key) {
                return false;
            }
            if (!$func($value, ...$args)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if arrays have the same values after sorting for comparison
     *
     * Returns `false` if fewer than two arrays are given.
     *
     * @param mixed[] ...$arrays
     */
    public static function sameValues(array ...$arrays): bool
    {
        if (count($arrays) < 2) {
            return false;
        }
        $last = null;
        foreach ($arrays as $array) {
            usort($array, fn($a, $b) => gettype($a) <=> gettype($b) ?: $a <=> $b);
            if ($last !== null && $last !== $array) {
                return false;
            }
            $last = $array;
        }
        return true;
    }

    /**
     * Check if arrays are the same after sorting by key
     *
     * Returns `false` if fewer than two arrays are given.
     *
     * @param mixed[] ...$arrays
     */
    public static function same(array ...$arrays): bool
    {
        if (count($arrays) < 2) {
            return false;
        }
        $last = null;
        foreach ($arrays as $array) {
            ksort($array, \SORT_STRING);
            if ($last !== null && $last !== $array) {
                return false;
            }
            $last = $array;
        }
        return true;
    }

    /**
     * Remove null values from an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<TKey,TValue|null> $array
     * @return array<TKey,TValue>
     */
    public static function whereNotNull(iterable $array): array
    {
        foreach ($array as $key => $value) {
            if ($value === null) {
                continue;
            }
            $filtered[$key] = $value;
        }
        return $filtered ?? [];
    }

    /**
     * Remove empty strings from an array of strings and Stringables
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param iterable<TKey,TValue> $array
     * @return array<TKey,TValue>
     */
    public static function whereNotEmpty(iterable $array): array
    {
        foreach ($array as $key => $value) {
            if ((string) $value === '') {
                continue;
            }
            $filtered[$key] = $value;
        }
        return $filtered ?? [];
    }

    /**
     * Implode values that remain in an array of strings and Stringables after
     * removing empty strings
     *
     * @param iterable<int|float|string|bool|Stringable|null> $array
     * @param string|null $characters Characters to trim from each value, `null`
     * to trim whitespace, or an empty string (the default) to trim nothing.
     */
    public static function implode(
        string $separator,
        iterable $array,
        ?string $characters = ''
    ): string {
        foreach ($array as $value) {
            $value = (string) $value;
            if ($characters !== '') {
                $value = $characters === null
                    ? trim($value)
                    : trim($value, $characters);
            }
            if ($value === '') {
                continue;
            }
            $filtered[] = $value;
        }
        return implode($separator, $filtered ?? []);
    }

    /**
     * Trim characters from each value in an array of strings and Stringables
     * before removing empty strings
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param iterable<TKey,TValue> $array
     * @param string|null $characters Characters to trim, `null` (the default)
     * to trim whitespace, or an empty string to trim nothing.
     * @return ($removeEmpty is false ? array<TKey,string> : list<string>)
     */
    public static function trim(
        iterable $array,
        ?string $characters = null,
        bool $removeEmpty = true
    ): array {
        foreach ($array as $key => $value) {
            $value = (string) $value;
            if ($characters !== '') {
                $value = $characters === null
                    ? trim($value)
                    : trim($value, $characters);
            }
            if ($removeEmpty) {
                if ($value !== '') {
                    $trimmed[] = $value;
                }
                continue;
            }
            $trimmed[$key] = $value;
        }
        return $trimmed ?? [];
    }

    /**
     * Make an array of strings and Stringables lowercase
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param iterable<TKey,TValue> $array
     * @return array<TKey,string>
     */
    public static function lower(iterable $array): array
    {
        foreach ($array as $key => $value) {
            $lower[$key] = Str::lower((string) $value);
        }
        return $lower ?? [];
    }

    /**
     * Make an array of strings and Stringables uppercase
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param iterable<TKey,TValue> $array
     * @return array<TKey,string>
     */
    public static function upper(iterable $array): array
    {
        foreach ($array as $key => $value) {
            $upper[$key] = Str::upper((string) $value);
        }
        return $upper ?? [];
    }

    /**
     * Make an array of strings and Stringables snake_case
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param iterable<TKey,TValue> $array
     * @return array<TKey,string>
     */
    public static function snakeCase(iterable $array): array
    {
        foreach ($array as $key => $value) {
            $snakeCase[$key] = Str::toSnakeCase((string) $value);
        }
        return $snakeCase ?? [];
    }

    /**
     * Cast non-scalar values in an array to strings
     *
     * Objects that implement {@see Stringable} are cast to a string. `null`
     * values are replaced with `$null`. Other non-scalar values are
     * JSON-encoded.
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool
     * @template TNull of TValue|null
     *
     * @param iterable<TKey,TValue|mixed[]|object|null> $array
     * @param TNull $null
     * @return array<TKey,TValue|TNull|string>
     */
    public static function toScalars(iterable $array, $null = null): array
    {
        foreach ($array as $key => $value) {
            if ($value === null) {
                $value = $null;
            } elseif (!is_scalar($value)) {
                if (Test::isStringable($value)) {
                    $value = (string) $value;
                } elseif ($value instanceof Jsonable) {
                    $value = $value->toJson(Json::ENCODE_FLAGS);
                } else {
                    $value = Json::stringify($value);
                }
            }
            $scalars[$key] = $value;
        }
        return $scalars ?? [];
    }

    /**
     * Get the offset (0-based) of a key in an array
     *
     * @template TKey of array-key
     *
     * @param array<TKey,mixed> $array
     * @param TKey $key
     * @throws OutOfRangeException if `$key` is not found in `$array`.
     */
    public static function keyOffset(array $array, $key): int
    {
        $offset = array_flip(array_keys($array))[$key] ?? null;
        if ($offset === null) {
            throw new OutOfRangeException(sprintf('Array key not found: %s', $key));
        }
        return $offset;
    }

    /**
     * Rename an array key without changing its offset
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TKey $key
     * @param TKey $newKey
     * @return array<TKey,TValue>
     */
    public static function rename(array $array, $key, $newKey): array
    {
        if (!array_key_exists($key, $array)) {
            throw new OutOfRangeException(sprintf('Array key not found: %s', $key));
        }
        if ($key === $newKey) {
            return $array;
        }
        return self::spliceByKey($array, $key, 1, [$newKey => $array[$key]]);
    }

    /**
     * Remove and/or replace part of an array by offset (0-based)
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TValue[]|TValue $replacement
     * @param array<TKey,TValue>|null $replaced
     * @param-out array<TKey,TValue> $replaced
     * @return array<TKey|int,TValue>
     */
    public static function splice(
        array $array,
        int $offset,
        ?int $length = null,
        $replacement = [],
        ?array &$replaced = null
    ): array {
        // $length can't be null in PHP 7.4
        if ($length === null) {
            $length = count($array);
        }
        $replaced = array_splice($array, $offset, $length, $replacement);
        return $array;
    }

    /**
     * Remove and/or replace part of an array by key
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TKey $key
     * @param array<TKey,TValue> $replacement
     * @param array<TKey,TValue>|null $replaced
     * @param-out array<TKey,TValue> $replaced
     * @return array<TKey,TValue>
     */
    public static function spliceByKey(
        array $array,
        $key,
        ?int $length = null,
        array $replacement = [],
        ?array &$replaced = null
    ): array {
        $keys = array_keys($array);
        $offset = array_flip($keys)[$key] ?? null;
        if ($offset === null) {
            throw new OutOfRangeException(sprintf('Array key not found: %s', $key));
        }
        // $length can't be null in PHP 7.4
        if ($length === null) {
            $length = count($array);
        }
        $replaced = array_combine(
            array_splice($keys, $offset, $length, array_keys($replacement)),
            array_splice($array, $offset, $length, $replacement),
        );
        return array_combine($keys, $array);
    }

    /**
     * Get an array that maps the values in an array to a given value
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param iterable<array-key,TKey> $array
     * @param TValue $value
     * @return ($value is true ? array<TKey,true> : array<TKey,TValue>)
     */
    public static function toIndex(iterable $array, $value = true): array
    {
        if (is_array($array)) {
            return array_fill_keys($array, $value);
        }
        foreach ($array as $key) {
            $index[$key] = $value;
        }
        return $index ?? [];
    }

    /**
     * Index an array by an identifier unique to each value
     *
     * @template TValue of ArrayAccess|array|object
     *
     * @param iterable<array-key,TValue> $array
     * @param array-key $key
     * @return TValue[]
     */
    public static function toMap(iterable $array, $key): array
    {
        foreach ($array as $item) {
            $map[
                is_array($item) || $item instanceof ArrayAccess
                    ? $item[$key]
                    : $item->$key
            ] = $item;
        }
        return $map ?? [];
    }

    /**
     * Apply a callback to a value for each of the elements of an array
     *
     * The return value of each call is passed to the next or returned to the
     * caller.
     *
     * Similar to {@see array_reduce()}.
     *
     * @template TKey of array-key
     * @template TValue
     * @template T
     *
     * @param callable(T, TValue, TKey): T $callback
     * @param iterable<TKey,TValue> $array
     * @param T $value
     * @return T
     */
    public static function with(callable $callback, iterable $array, $value)
    {
        foreach ($array as $key => $arrayValue) {
            $value = $callback($value, $arrayValue, $key);
        }
        return $value;
    }

    /**
     * Flatten a multi-dimensional array
     *
     * @param iterable<mixed> $array
     * @param int $limit The maximum number of dimensions to flatten. Default:
     * `-1` (no limit)
     * @return mixed[]
     */
    public static function flatten(iterable $array, int $limit = -1): array
    {
        $flattened = [];
        foreach ($array as $value) {
            if (!is_iterable($value) || !$limit) {
                $flattened[] = $value;
                continue;
            }
            if ($limit - 1) {
                $value = self::flatten($value, $limit - 1);
            }
            foreach ($value as $value) {
                $flattened[] = $value;
            }
        }
        return $flattened;
    }

    /**
     * If a value is not an array, wrap it in one
     *
     * @template T
     *
     * @param T $value
     * @return (T is null ? array{} : (T is array ? T : array{T}))
     */
    public static function wrap($value): array
    {
        if ($value === null) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }

    /**
     * If a value is not a list, wrap it in one
     *
     * @template T
     *
     * @param T $value
     * @return (T is null ? array{} : (T is list ? T : array{T}))
     */
    public static function listWrap($value): array
    {
        if ($value === null) {
            return [];
        }
        return self::isList($value, true) ? $value : [$value];
    }

    /**
     * Remove arrays wrapped around a value
     *
     * @param mixed $value
     * @param int $limit The maximum number of arrays to remove. Default: `-1`
     * (no limit)
     * @return mixed
     */
    public static function unwrap($value, int $limit = -1)
    {
        while (
            $limit
            && is_array($value)
            && count($value) === 1
            && array_key_first($value) === 0
        ) {
            $value = $value[0];
            $limit--;
        }
        return $value;
    }
}
