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
     * Create a map from a list of items
     *
     * Something like: `[ ITEM[$key] => ITEM, ... ]`
     *
     * @param array<int,array|object> $list
     * @param string $key
     * @return array<string,array|object>
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
     * @param string $string The string being hashed.
     * @return string
     */
    public static function Hash(string $string): string
    {
        // xxHash isn't supported until PHP 8.1, so MD5 is the best fit
        return hash("md5", $string);
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
     * @return string
     */
    public static function Normalise(string $text, bool $toUpper = true, ?string $stripPattern = "\\.", ?string $spacePattern = "[^A-Z0-9]")
    {
        if ($toUpper)
        {
            $text = mb_strtoupper($text);
        }

        $text = mb_ereg_replace("&", " AND ", $text);
        $text = mb_ereg_replace("[\342\200\220\342\200\221\342\200\223\342\200\222]", "-", $text);

        if ($stripPattern)
        {
            $text = mb_ereg_replace($stripPattern, "", $text);
        }

        if ($spacePattern)
        {
            $text = mb_ereg_replace($spacePattern, " ", $text);
        }

        $text = mb_ereg_replace("\\s+", " ", $text);

        return $text;
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
        if (!$closure instanceof Closure)
        {
            $closure = Closure::fromCallable($closure);
        }

        $closure = new ReflectionFunction($closure);

        // ReflectionFunction::__toString() is unambiguous and consistent
        return self::Hash((string)$closure);
    }
}

