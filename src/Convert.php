<?php

declare(strict_types=1);

namespace Lkrms;

/**
 * Functions for type wrangling
 *
 * @package Lkrms
 */
class Convert
{
    /**
     * If a variable isn't an array, make it the first element of one
     *
     * @param mixed $value The variable being checked.
     * @return array Either `$value` or `[$value]`.
     */
    public static function AnyToArray($value): array
    {
        return is_array($value) ? $value : [
            $value
        ];
    }

    /**
     * If a variable is 'falsey', make it null
     *
     * @param mixed $value The variable being checked.
     * @return mixed Either `$value` or `null`.
     */
    public static function EmptyToNull($value)
    {
        return ! $value ? null : $value;
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
            if ( ! is_scalar($value))
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
}

