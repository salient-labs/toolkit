<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Contract\Core\Jsonable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Format data for humans
 *
 * @api
 */
final class Format extends AbstractUtility
{
    private const BINARY_UNITS = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
    private const DECIMAL_UNITS = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB', 'RB', 'QB'];

    /**
     * Format values in a list
     *
     * @param mixed[]|null $list
     * @param string $format Passed to {@see sprintf()} with each value.
     * @param int $indent Spaces to add after newlines in values.
     */
    public static function list(
        ?array $list,
        string $format = "- %s\n",
        int $indent = 2
    ): string {
        if ($list === null || !$list) {
            return '';
        }
        $indent = $indent > 0 ? str_repeat(' ', $indent) : '';
        $string = '';
        foreach ($list as $value) {
            $value = self::value($value);
            if ($indent !== '') {
                $value = str_replace("\n", "\n" . $indent, $value);
            }
            $string .= sprintf($format, $value);
        }
        return $string;
    }

    /**
     * Format keys and values in an array
     *
     * @param mixed[]|null $array
     * @param string $format Passed to {@see sprintf()} with each key and value.
     * @param int $indent Spaces to add after newlines in values.
     */
    public static function array(
        ?array $array,
        string $format = "%s: %s\n",
        int $indent = 4
    ): string {
        if ($array === null || !$array) {
            return '';
        }
        $indent = $indent > 0 ? str_repeat(' ', $indent) : '';
        $string = '';
        foreach ($array as $key => $value) {
            $value = self::value($value);
            if ($indent !== '') {
                $value = str_replace("\n", "\n" . $indent, $value, $count);
                if ($count) {
                    $value = "\n" . $indent . $value;
                }
            }
            $string .= sprintf($format, $key, $value);
        }
        return $string;
    }

    /**
     * Format a value
     *
     * @param mixed $value
     */
    public static function value($value): string
    {
        if ($value === null) {
            return '';
        }
        if (Test::isStringable($value)) {
            return Str::setEol((string) $value);
        }
        if (is_bool($value)) {
            return self::bool($value);
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof Jsonable) {
            return $value->toJson(Json::ENCODE_FLAGS);
        }
        return Json::stringify($value);
    }

    /**
     * Format a boolean as "true" or "false"
     */
    public static function bool(?bool $value): string
    {
        return $value === null ? '' : ($value ? 'true' : 'false');
    }

    /**
     * Format a boolean as "yes" or "no"
     */
    public static function yn(?bool $value): string
    {
        return $value === null ? '' : ($value ? 'yes' : 'no');
    }

    /**
     * Format a date and time without redundant information
     */
    public static function date(
        ?DateTimeInterface $date,
        string $before = '[',
        ?string $after = ']',
        ?string $thisYear = null
    ): string {
        if ($date === null) {
            return '';
        }

        $thisYear ??= date('Y');

        $date = Date::maybeSetTimezone($date);

        // - Start with "Tue 9 Apr"
        // - Add " 2024" if `$date` is not in the current year
        // - Add " 16:52:31 AEST" if the time is not midnight
        $format = 'D j M';
        if ($date->format('Y') !== $thisYear) {
            $format .= ' Y';
        }
        if ($date->format('H:i:s') !== '00:00:00') {
            $format .= ' H:i:s T';
        }

        return Str::wrap($date->format($format), $before, $after);
    }

    /**
     * Format a date and time range without redundant information
     */
    public static function dateRange(
        ?DateTimeInterface $from,
        ?DateTimeInterface $to,
        string $delimiter = '–',
        string $before = '[',
        ?string $after = ']',
        ?string $thisYear = null
    ): string {
        if ($from === null && $to === null) {
            return '';
        }

        $thisYear ??= date('Y');

        if ($from === null || $to === null) {
            return sprintf(
                '%s%s%s',
                self::date($from, $before, $after, $thisYear),
                $delimiter,
                self::date($to, $before, $after, $thisYear),
            );
        }

        $from = Date::maybeSetTimezone($from);
        $to = Date::maybeSetTimezone($to);

        [$fromTimezone, $fromYear, $fromTime] =
            [$from->format('T'), $from->format('Y'), $from->format('H:i:s')];
        [$toTimezone, $toYear, $toTime] =
            [$to->format('T'), $to->format('Y'), $to->format('H:i:s')];

        // - Start with "Tue 9 Apr"
        // - Add " 2024" to both if they are in different years, or once if they
        //   are not in the current year
        // - If the time of `$from` or `$to` is not midnight:
        //   - Add " 16:52:31" to both
        //   - Add " AEST" to both if they are in different timezones, or once
        //     if they are in the same timezone
        $fromFormat = $toFormat = 'D j M';
        if ($fromYear !== $toYear) {
            $fromFormat = $toFormat .= ' Y';
        } elseif ($fromYear !== $thisYear) {
            $toFormat .= ' Y';
        }
        if ($fromTime !== '00:00:00' || $toTime !== '00:00:00') {
            $fromFormat .= ' H:i:s';
            $toFormat .= ' H:i:s T';
            if ($fromTimezone !== $toTimezone) {
                $fromFormat .= ' T';
            }
        }

        return sprintf(
            '%s%s%s',
            Str::wrap($from->format($fromFormat), $before, $after),
            $delimiter,
            Str::wrap($to->format($toFormat), $before, $after),
        );
    }

    /**
     * Format a size in bytes by rounding to an appropriate binary or decimal
     * unit (B, KiB/kB, MiB/MB, GiB/GB, ...)
     */
    public static function bytes(
        ?int $bytes,
        int $precision = 3,
        bool $binary = true
    ): string {
        if ($bytes === null) {
            return '';
        }
        if ($bytes < 0) {
            throw new InvalidArgumentException('$bytes cannot be less than zero');
        }
        if ($precision < 0) {
            throw new InvalidArgumentException('$precision cannot be less than zero');
        }

        [$base, $units] = $binary
            ? [1024, self::BINARY_UNITS]
            : [1000, self::DECIMAL_UNITS];
        $maxPower = count($units) - 1;
        $power = $bytes
            ? min($maxPower, (int) (log($bytes) / log($base)))
            : 0;
        $bytes = $bytes / $base ** $power;

        if ($bytes >= 1000 && $precision && $power < $maxPower) {
            $power++;
            $bytes /= $base;
        }

        return sprintf(
            $precision && $power ? "%.{$precision}f%s" : '%d%s',
            $precision ? (int) ($bytes * 10 ** $precision) / 10 ** $precision : (int) $bytes,
            $units[$power],
        );
    }
}
