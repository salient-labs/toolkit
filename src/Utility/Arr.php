<?php declare(strict_types=1);

namespace Lkrms\Utility;

/**
 * Manipulate arrays
 */
final class Arr
{
    /**
     * Get the first value in an array
     *
     * @template TValue
     *
     * @param array<array-key,TValue> $array
     *
     * @return TValue|null
     */
    public static function first(array $array)
    {
        if (!$array) {
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
     *
     * @return TValue|null
     */
    public static function last(array $array)
    {
        if (!$array) {
            return null;
        }
        return end($array);
    }

    /**
     * Shift an element off the beginning of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TValue|null $shifted
     *
     * @return array<TKey,TValue>
     */
    public static function shift(array $array, &$shifted = null): array
    {
        $shifted = array_shift($array);
        return $array;
    }

    /**
     * Add one or more elements to the beginning of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TValue ...$values
     *
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
     *
     * @return array<TKey,TValue>
     */
    public static function pop(array $array, &$popped = null): array
    {
        $popped = array_pop($array);
        return $array;
    }

    /**
     * Push one or more values onto the end of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param TValue ...$values
     *
     * @return array<TKey,TValue>
     */
    public static function push(array $array, ...$values): array
    {
        array_push($array, ...$values);
        return $array;
    }

    /**
     * Sort an array by value
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param (callable(TValue, TValue): int)|int-mask-of<\SORT_REGULAR|\SORT_NUMERIC|\SORT_STRING|\SORT_LOCALE_STRING|\SORT_NATURAL|\SORT_FLAG_CASE> $callbackOrFlags
     *
     * @return list<TValue>|array<TKey,TValue>
     * @phpstan-return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public static function sort(
        array $array,
        bool $preserveKeys = false,
        $callbackOrFlags = \SORT_REGULAR
    ): array {
        if (is_callable($callbackOrFlags)) {
            $callback = $callbackOrFlags;

            if ($preserveKeys) {
                uasort($array, $callback);
                return $array;
            }

            usort($array, $callback);
            return $array;
        }

        $flags = $callbackOrFlags;

        if ($preserveKeys) {
            asort($array, $flags);
            return $array;
        }

        sort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by value in descending order
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param int-mask-of<\SORT_REGULAR|\SORT_NUMERIC|\SORT_STRING|\SORT_LOCALE_STRING|\SORT_NATURAL|\SORT_FLAG_CASE> $flags
     *
     * @return list<TValue>|array<TKey,TValue>
     * @phpstan-return ($preserveKeys is true ? array<TKey,TValue> : list<TValue>)
     */
    public static function sortDesc(
        array $array,
        bool $preserveKeys = false,
        int $flags = \SORT_REGULAR
    ): array {
        if (!$preserveKeys) {
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
     * @param (callable(TValue, TValue): int)|int-mask-of<\SORT_REGULAR|\SORT_NUMERIC|\SORT_STRING|\SORT_LOCALE_STRING|\SORT_NATURAL|\SORT_FLAG_CASE> $callbackOrFlags
     *
     * @return array<TKey,TValue>
     */
    public static function sortByKey(
        array $array,
        $callbackOrFlags = \SORT_REGULAR
    ): array {
        if (is_callable($callbackOrFlags)) {
            $callback = $callbackOrFlags;

            uksort($array, $callback);
            return $array;
        }

        $flags = $callbackOrFlags;

        ksort($array, $flags);
        return $array;
    }

    /**
     * Sort an array by key in descending order
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue> $array
     * @param int-mask-of<\SORT_REGULAR|\SORT_NUMERIC|\SORT_STRING|\SORT_LOCALE_STRING|\SORT_NATURAL|\SORT_FLAG_CASE> $flags
     *
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
     * Remove duplicate values from an array without preserving keys
     *
     * @template TValue
     *
     * @param array<array-key,TValue> $array
     *
     * @return list<TValue>
     */
    public static function unique(array $array): array
    {
        $unique = [];
        foreach ($array as $value) {
            if (in_array($value, $unique, true)) {
                continue;
            }
            $unique[] = $value;
        }
        return $unique;
    }

    /**
     * True if a value is an array with consecutive integer keys numbered from 0
     *
     * @param mixed $value
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
     * True if a value is an array with integer keys
     *
     * @param mixed $value
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
     * True if a value is an array of integers or an array of strings
     *
     * @param mixed $value
     */
    public static function ofArrayKey($value, bool $orEmpty = false): bool
    {
        return
            self::ofInt($value, $orEmpty) ||
            self::ofString($value);
    }

    /**
     * True if a value is a list of integers or a list of strings
     *
     * @param mixed $value
     */
    public static function listOfArrayKey($value, bool $orEmpty = false): bool
    {
        return
            self::listOfInt($value, $orEmpty) ||
            self::listOfString($value);
    }

    /**
     * True if a value is an array of integers
     *
     * @param mixed $value
     */
    public static function ofInt($value, bool $orEmpty = false): bool
    {
        return self::doOfType('is_int', $value, $orEmpty, false);
    }

    /**
     * True if a value is a list of integers
     *
     * @param mixed $value
     */
    public static function listOfInt($value, bool $orEmpty = false): bool
    {
        return self::doOfType('is_int', $value, $orEmpty, true);
    }

    /**
     * True if a value is an array of strings
     *
     * @param mixed $value
     */
    public static function ofString($value, bool $orEmpty = false): bool
    {
        return self::doOfType('is_string', $value, $orEmpty, false);
    }

    /**
     * True if a value is a list of strings
     *
     * @param mixed $value
     */
    public static function listOfString($value, bool $orEmpty = false): bool
    {
        return self::doOfType('is_string', $value, $orEmpty, true);
    }

    /**
     * @param mixed $value
     */
    private static function doOfType(string $func, $value, bool $orEmpty, bool $requireList): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (!$value) {
            return $orEmpty;
        }
        $i = 0;
        foreach ($value as $key => $value) {
            if ($requireList && $i++ !== $key) {
                return false;
            }
            if (!$func($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Remove null values from an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param array<TKey,TValue|null> $array
     *
     * @return array<TKey,TValue>
     */
    public static function whereNotNull(array $array): array
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
     * @template TValue of int|float|string|bool|\Stringable|null
     *
     * @param array<TKey,TValue> $array
     *
     * @return array<TKey,TValue>
     */
    public static function whereNotEmpty(array $array): array
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
     * removing whitespace from the beginning and end of each value and
     * optionally removing empty strings
     *
     * @param array<int|float|string|bool|\Stringable|null> $array
     * @param string|null $characters Optionally specify characters to remove
     * instead of whitespace.
     */
    public static function trimAndImplode(
        string $separator,
        array $array,
        ?string $characters = null,
        bool $removeEmpty = true
    ): string {
        foreach ($array as $value) {
            $value =
                $characters === null
                    ? trim((string) $value)
                    : trim((string) $value, $characters);
            if ($removeEmpty && $value === '') {
                continue;
            }
            $trimmed[] = $value;
        }
        return implode($separator, $trimmed ?? []);
    }

    /**
     * Implode values that remain in an array of strings and Stringables after
     * removing empty strings
     *
     * @param array<int|float|string|bool|\Stringable|null> $array
     *
     * @return string
     */
    public static function implode(string $separator, array $array): string
    {
        foreach ($array as $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $filtered[] = $value;
        }
        return implode($separator, $filtered ?? []);
    }

    /**
     * Remove whitespace from the beginning and end of each value in an array
     * of strings and Stringables before optionally removing empty strings
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|\Stringable|null
     *
     * @param array<TKey,TValue> $array
     * @param string|null $characters Optionally specify characters to remove
     * instead of whitespace.
     *
     * @return array<TKey,string>
     */
    public static function trim(
        array $array,
        ?string $characters = null,
        bool $removeEmpty = true
    ): array {
        foreach ($array as $key => $value) {
            $value =
                $characters === null
                    ? trim((string) $value)
                    : trim((string) $value, $characters);
            if ($removeEmpty && $value === '') {
                continue;
            }
            $trimmed[$key] = $value;
        }
        return $trimmed ?? [];
    }

    /**
     * Apply a callback to the elements of an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param callable(TValue, TKey): mixed $callback
     * @param array<TKey,TValue> $array
     *
     * @return array<TKey,TValue>
     */
    public static function forEach(callable $callback, array $array): array
    {
        foreach ($array as $key => $value) {
            $callback($value, $key);
        }
        return $array;
    }

    /**
     * Apply a callback to a value for each of the elements of an array
     *
     * The return value of each call is passed to the next or returned to the
     * caller.
     *
     * Similar to `array_reduce()`.
     *
     * @template TKey of array-key
     * @template TValue
     * @template T
     *
     * @param callable(T, TValue, TKey): T $callback
     * @param array<TKey,TValue> $array
     * @param T $value
     *
     * @return T
     */
    public static function with(callable $callback, array $array, $value)
    {
        foreach ($array as $key => $arrayValue) {
            $value = $callback($value, $arrayValue, $key);
        }
        return $value;
    }

    /**
     * If a value is not an array, wrap it in one
     *
     * @param mixed $value
     *
     * @return mixed[]
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
     * @param mixed $value
     *
     * @return mixed[]
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
     *
     * @return mixed
     */
    public static function unwrap($value, int $limit = -1)
    {
        while (
            $limit &&
            is_array($value) &&
            count($value) === 1 &&
            array_key_first($value) === 0
        ) {
            $value = $value[0];
            $limit--;
        }
        return $value;
    }
}
