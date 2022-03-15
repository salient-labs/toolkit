<?php

declare(strict_types=1);

namespace Lkrms;

/**
 * Data in, readable text out
 *
 * @package Lkrms
 */
class Format
{
    /**
     * Format an array's keys and values
     *
     * Non-scalar values are converted to JSON first.
     *
     * @param array $array
     * @param int $indentSpaces The number of spaces to add after any newlines
     * in `$array`.
     * @param string $format The format to pass to `sprintf`. Must include two
     * string conversion specifications (`%s`).
     * @return string
     */
    public static function array(
        array $array,
        string $format    = "%s: %s\n",
        int $indentSpaces = 4
    ): string
    {
        $indent = str_repeat(" ", $indentSpaces);
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

            $string .= sprintf($format, $key, $value);
        }

        return $string;
    }

    /**
     * Return "true" if a boolean is true, "false" if it's not
     *
     * @param bool $value
     * @return string Either `"true"` or `"false"`.
     */
    public static function bool(bool $value)
    {
        return $value ? "true" : "false";
    }
}

