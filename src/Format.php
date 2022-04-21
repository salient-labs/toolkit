<?php

declare(strict_types=1);

namespace Lkrms;

use DateTime;
use UnexpectedValueException;

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
    public static function bool(bool $value): string
    {
        return $value ? "true" : "false";
    }

    private static function getBetween(string $between): array
    {
        if (strlen($between) % 2)
        {
            throw new UnexpectedValueException('String length is not even: ' . $between);
        }

        return [
            substr($between, 0, strlen($between) / 2),
            substr($between, strlen($between) / 2)
        ];
    }

    public static function date(DateTime $date, string $between = '[]'): string
    {
        list ($l, $r) = self::getBetween($between);

        $noYear = date('Y') == $date->format('Y');
        $noTime = $date->format('H:i:s') == '00:00:00';

        $format = 'D j M' . ($noYear ? '' : ' Y') . ($noTime ? '' : ' H:i:s T');

        return $l . $date->format($format) . $r;
    }

    public static function dateRange(
        DateTime $from,
        DateTime $to,
        string $between   = '[]',
        string $delimiter = "\u{2013}"
    ): string
    {
        list ($l, $r) = self::getBetween($between);

        $sameYear = ($year = $from->format('Y')) == $to->format('Y');
        $sameZone = $from->getTimezone() == $to->getTimezone();
        $noYear   = $sameYear && date('Y') == $year;
        $noTime   = $sameZone && $from->format('H:i:s') == '00:00:00' && $to->format('H:i:s') == '00:00:00';

        $fromFormat = 'D j M' . ($noYear ? '' : ' Y') . ($noTime ? '' : ' H:i:s' . ($sameZone ? '' : ' T'));
        $toFormat   = $fromFormat . ($noTime || !$sameZone ? '' : ' T');

        return $l . $from->format($fromFormat) . "$r$delimiter$l" . $to->format($toFormat) . $r;
    }
}
