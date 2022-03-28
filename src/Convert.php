<?php

declare(strict_types=1);

namespace Lkrms;

use Closure;
use Stringable;
use UnexpectedValueException;

/**
 * Data wrangling
 *
 * @package Lkrms
 */
abstract class Convert
{
    /**
     * See mb_regex_set_options
     *
     * l = find longest matches, z = Perl syntax
     */
    public const MB_REGEX_OPTIONS = "lz";

    /**
     * If a variable isn't an indexed array, make it the first element of one
     *
     * @param mixed $value The variable being checked.
     * @return array Either `$value` or `[$value]`.
     */
    public static function toArray($value): array
    {
        return Test::isIndexedArray($value) ? $value : [$value];
    }

    /**
     * @deprecated Use {@see Convert::toArray()} instead
     */
    public static function anyToArray($value): array
    {
        return self::toArray($value);
    }

    /**
     * If a variable isn't a list, make it the first element of one
     *
     * @param mixed $value The variable being checked.
     * @return array Either `$value` or `[$value]`.
     */
    public static function toList($value): array
    {
        return Test::isListArray($value) ? $value : [$value];
    }

    /**
     * @deprecated Use {@see Convert::toList()} instead
     */
    public static function anyToList($value): array
    {
        return self::toList($value);
    }

    /**
     * If a variable is 'falsey', make it null
     *
     * @param mixed $value The variable being checked.
     * @return mixed Either `$value` or `null`.
     */
    public static function emptyToNull($value)
    {
        return !$value ? null : $value;
    }

    /**
     * @deprecated Use {@see Format::bool()} instead
     */
    public static function boolToString(bool $value)
    {
        return Format::bool($value);
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
     * @param array $list
     * @param string|Closure $key Either the index or property name to use when
     * retrieving keys from arrays and objects in `$list`, or a closure that
     * returns a key for each item in `$list`.
     * @return array
     */
    public static function listToMap(array $list, $key): array
    {
        if ($key instanceof Closure)
        {
            $callback = $key;
        }
        else
        {
            $callback = function ($item) use ($key)
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

        return array_combine(
            array_map($callback, $list),
            $list
        );
    }

    /**
     * @deprecated Use {@see Format::array()} instead
     */
    public static function arrayToString(array $array): string
    {
        return Format::array($array);
    }

    /**
     * Remove zero-width values from an array before imploding it
     *
     * @param string $separator
     * @param array $array
     * @return string
     */
    public static function sparseToString(string $separator, array $array): string
    {
        return implode($separator, array_filter(
            $array,
            function ($value) { return strlen((string)$value) > 0; }
        ));
    }

    /**
     * @deprecated Use {@see Convert::sparseToString()} instead
     */
    public static function implodeNotEmpty(string $separator, array $array): string
    {
        return self::sparseToString($separator, $array);
    }

    /**
     * Convert a scalar to a string
     *
     * @param mixed $value
     * @return string|false Returns `false` if `$value` is not a scalar
     */
    public static function scalarToString($value)
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
     * If a number is 1, return $singular, otherwise return $plural
     *
     * @param int $number
     * @param string $singular
     * @param string|null $plural If `null`, `{$singular}s` will be used instead
     * @param bool $includeNumber Return `$number $noun` instead of `$noun`
     * @return string
     */
    public static function numberToNoun(int $number, string $singular, string $plural = null, bool $includeNumber = false): string
    {
        if ($number == 1)
        {
            $noun = $singular;
        }
        else
        {
            $noun = is_null($plural) ? $singular . "s" : $plural;
        }

        if ($includeNumber)
        {
            return "$number $noun";
        }

        return $noun;
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
     * @param array<int|string,string|Stringable> $value
     * @return string[]
     */
    public static function toStrings(...$value): array
    {
        return array_map(function ($string) { return (string)$string; }, $value);
    }

    /**
     * @deprecated Use {@see Generate::hash()} instead
     */
    public static function hash(...$value): string
    {
        return Generate::hash(...$value);
    }

    /**
     * Convert an identifier to snake_case
     *
     * @param string $text The identifier to convert.
     * @return string
     */
    public static function toSnakeCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "_", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1_$2', $text);

        return strtolower($text);
    }

    /**
     * @deprecated Use {@see Convert::toSnakeCase()} instead
     */
    public static function identifierToSnakeCase(string $text): string
    {
        return self::toSnakeCase($text);
    }

    /**
     * Convert an identifier to kebab-case
     *
     * @param string $text
     * @return string
     */
    public static function toKebabCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "-", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1-$2', $text);

        return strtolower($text);
    }

    /**
     * Convert an identifier to PascalCase
     *
     * @param string $text
     * @return string
     */
    public static function toPascalCase(string $text): string
    {
        $text = preg_replace_callback(
            '/([[:upper:]]?[[:lower:][:digit:]]+|([[:upper:]](?![[:lower:]]))+)/',
            function (array $matches) { return ucfirst(strtolower($matches[0])); },
            $text
        );

        return preg_replace("/[^[:alnum:]]+/", "", $text);
    }

    /**
     * Clean up a string for comparison with other strings
     *
     * Normalised values may vary with each release and should be considered
     * transient.
     *
     * @param string $text The string being normalised.
     * @param bool $toUpper If true, make `$text` uppercase.
     * @param null|string $stripPattern Matching characters are removed.
     * @param null|string $spacePattern Matching characters are replaced with
     * whitespace.
     * @param bool $trim If true, remove leading and trailing whitespace.
     * @return string
     */
    public static function toNormal(
        string $text,
        bool $toUpper         = true,
        ?string $stripPattern = null,
        ?string $spacePattern = "[^a-zA-Z0-9]",
        bool $trim            = true
    )
    {
        if ($toUpper)
        {
            $text = mb_strtoupper($text);
        }

        $text = mb_ereg_replace("&", " AND ", $text, self::MB_REGEX_OPTIONS);

        // Replace (some) Unicode hyphens with plain ones; more here:
        // https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=%5Cp%7BDash%7D&abb=on&esc=on&g=&i=
        $text = mb_ereg_replace("[\u{2010}-\u{2015}]", "-", $text, self::MB_REGEX_OPTIONS);

        if ($stripPattern)
        {
            $text = mb_ereg_replace($stripPattern, "", $text, self::MB_REGEX_OPTIONS);
        }

        if ($spacePattern)
        {
            $text = mb_ereg_replace($spacePattern, " ", $text, self::MB_REGEX_OPTIONS);
        }

        if ($trim)
        {
            $text = mb_ereg_replace("(^\\s+|\\s+\$)", "", $text, self::MB_REGEX_OPTIONS);
        }

        $text = mb_ereg_replace("\\s+", " ", $text, self::MB_REGEX_OPTIONS);

        return $text;
    }

    /**
     * @deprecated Use {@see Convert::toNormal()} instead
     */
    public static function normalise(
        string $text,
        bool $toUpper         = true,
        ?string $stripPattern = null,
        ?string $spacePattern = "[^a-zA-Z0-9]",
        bool $trim            = true
    )
    {
        return self::toNormal($text, $toUpper, $stripPattern, $spacePattern, $trim);
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
    public static function objectToArray(object $object)
    {
        return get_object_vars($object);
    }

    private static function _dataToQuery(
        array $data,
        bool $forceNumericKeys,
        string & $query = null,
        string $name    = "",
        string $format  = "%s"
    ): string
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

                $query .= ($query ? "&" : "") . urlencode($name . $_name) . "=" . urlencode((string)$value);

                continue;
            }
            elseif (!$forceNumericKeys && Test::isListArray($value))
            {
                $_format = "[]";
            }
            else
            {
                $_format = "[%s]";
            }

            self::_dataToQuery($value, $forceNumericKeys, $query, $name . $_name, $_format);
        }

        return $query;
    }

    /**
     * A more API-friendly http_build_query
     *
     * @param array $data
     * @param bool $forceNumericKeys
     * @return string
     */
    public static function dataToQuery(array $data, bool $forceNumericKeys = false): string
    {
        return self::_dataToQuery($data, $forceNumericKeys);
    }

    /**
     * @deprecated Use {@see Convert::dataToQuery()} instead
     */
    public static function httpBuildQuery(array $data, bool $forceNumericKeys = false): string
    {
        return self::dataToQuery($data, $forceNumericKeys);
    }

    /**
     * @deprecated Use {@see Generate::closureHash()} instead
     */
    public static function closureToHash(callable $closure): string
    {
        return Generate::closureHash($closure);
    }
}

