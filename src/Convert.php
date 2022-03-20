<?php

declare(strict_types=1);

namespace Lkrms;

use Closure;
use ReflectionFunction;
use UnexpectedValueException;

/**
 * Type wrangling
 *
 * @package Lkrms
 */
class Convert
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
    public static function AnyToArray($value): array
    {
        return Test::IsIndexedArray($value) ? $value : [$value];
    }

    /**
     * If a variable isn't a list, make it the first element of one
     *
     * @param mixed $value The variable being checked.
     * @return array Either `$value` or `[$value]`.
     */
    public static function AnyToList($value): array
    {
        return Test::IsListArray($value) ? $value : [$value];
    }

    /**
     * If a variable is 'falsey', make it null
     *
     * @param mixed $value The variable being checked.
     * @return mixed Either `$value` or `null`.
     */
    public static function EmptyToNull($value)
    {
        return !$value ? null : $value;
    }

    /**
     * Return `'true'` if a boolean is true, `'false'` if it's not
     *
     * @param bool $value The variable being checked.
     * @return string Either `'true'` or `'false'`.
     */
    public static function BoolToString(bool $value)
    {
        return $value ? "true" : "false";
    }

    /**
     * Create a map from a list of objects or arrays
     *
     * For example, to map from each array's `id` to the array itself:
     *
     * ```php
     * $list = [
     *     ['id' => 38, 'name' => 'Amir'],
     *     ['id' => 32, 'name' => 'Greta'],
     *     ['id' => 71, 'name' => 'Terry'],
     * ];
     *
     * $map = Convert::ListToMap($list, 'id');
     *
     * print_r($map);
     * ```
     *
     * ```
     * Array
     * (
     *     [38] => Array
     *         (
     *             [id] => 38
     *             [name] => Amir
     *         )
     *
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
     * @param array<int,array|object> $list
     * @param string $key
     * @return array<int|string,array|object>
     */
    public static function ListToMap(array $list, string $key): array
    {
        return array_combine(
            array_map(
                function ($item) use ($key)
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
                },
                $list
            ),
            $list
        );
    }

    /**
     * Format an array's keys and values
     *
     * @param array $array The array to format.
     * @return string
     */
    public static function ArrayToString(array $array): string
    {
        $indent = str_repeat(" ", 4);
        $string = "";

        foreach ($array as $key => $value)
        {
            if (!is_scalar($value))
            {
                $value = json_encode($value);
            }

            $value = str_replace("\r\n", "\n", (string)$value);
            $value = str_replace("\n", PHP_EOL . $indent, $value, $count);

            if ($count)
            {
                $value = PHP_EOL . $indent . $value;
            }

            $string .= sprintf("%s: %s\n", $key, $value);
        }

        return $string;
    }

    /**
     * Remove zero-width values from an array before imploding it
     *
     * @param string $separator
     * @param array $array
     * @return string
     */
    public static function ImplodeNotEmpty(string $separator, array $array): string
    {
        return implode($separator, array_filter($array,
            function ($value)
            {
                return strlen((string)$value) > 0;
            }));
    }

    /**
     * Convert a scalar to a string
     *
     * @param mixed $value
     * @return string|false Returns `false` if `$value` is not a scalar
     */
    public static function ScalarToString($value)
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
     * @param int           $number
     * @param string        $singular
     * @param string|null   $plural         If `null`, `{$singular}s` will be used instead
     * @param bool          $includeNumber  Return `$number $noun` instead of `$noun`
     * @return string
     */
    public static function NumberToNoun(int $number, string $singular, string $plural = null, bool $includeNumber = false): string
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
     * @param string $size From the PHP FAQ: "The available options are K (for Kilobytes), M (for Megabytes) and G (for
     * Gigabytes), and are all case-insensitive."
     * @return int
     */
    public static function SizeToBytes(string $size): int
    {
        if (!preg_match('/^(.+?)([KMG]?)$/', strtoupper($size), $match) || !is_numeric($match[1]))
        {
            throw new UnexpectedValueException("Invalid shorthand: '$size'");
        }

        $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3];

        return (int)($match[1] * (1024 ** $power[$match[2]]));
    }

    /**
     * Generate a unique non-crypto hash
     *
     * @param string[] $text One or more strings to hash.
     * @return string
     */
    public static function Hash(string...$text): string
    {
        // xxHash isn't supported until PHP 8.1, so MD5 is the best fit
        return hash("md5", implode("\000", $text));
    }

    /**
     * Convert a multiple-word identifier to snake_case
     *
     * @param string $text The identifier to convert.
     * @return string
     */
    public static function IdentifierToSnakeCase(string $text): string
    {
        $text = preg_replace("/[^[:alnum:]]+/", "_", $text);
        $text = preg_replace("/([[:lower:]])([[:upper:]])/", '$1_$2', $text);

        return strtolower($text);
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
    public static function Normalise(
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
     * A wrapper for get_object_vars
     *
     * Because you can't exclude `private` and `protected` properties from
     * inside the class. (Not easily, anyway.)
     *
     * @param object $object
     * @return array
     */
    public static function ObjectToArray(object $object)
    {
        return get_object_vars($object);
    }

    private static function _HttpBuildQuery(
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
            elseif (!$forceNumericKeys && Test::IsListArray($value))
            {
                $_format = "[]";
            }
            else
            {
                $_format = "[%s]";
            }

            self::_HttpBuildQuery($value, $forceNumericKeys, $query, $name . $_name, $_format);
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
    public static function HttpBuildQuery(array $data, bool $forceNumericKeys = false): string
    {
        return self::_HttpBuildQuery($data, $forceNumericKeys);
    }

    /**
     * Returns a hash that uniquely identifies a Closure (or any other callable)
     *
     * @param callable $closure
     * @return string
     */
    public static function ClosureToHash(callable $closure): string
    {
        if (!($closure instanceof Closure))
        {
            $closure = Closure::fromCallable($closure);
        }

        $closure = new ReflectionFunction($closure);

        // ReflectionFunction::__toString() is unambiguous and consistent
        return self::Hash((string)$closure);
    }
}

