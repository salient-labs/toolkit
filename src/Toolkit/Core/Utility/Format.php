<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Lkrms\Contract\Jsonable;
use Salient\Core\AbstractUtility;
use DateTimeInterface;

/**
 * Make data human-readable
 */
final class Format extends AbstractUtility
{
    /**
     * Format values in a list
     *
     * @param list<mixed> $list
     * @param string $format The format passed to `sprintf` for each value. Must
     * include a string conversion specification (`%s`).
     * @param int $indent Spaces to add after newlines in `$list` values.
     */
    public static function list(array $list, string $format = "- %s\n", int $indent = 2): string
    {
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
     * Format an array's keys and values
     *
     * @param mixed[] $array
     * @param string $format The format passed to `sprintf` for each value. Must
     * include two string conversion specifications (`%s`).
     * @param int $indent Spaces to add after newlines in `$array` values.
     */
    public static function array(array $array, string $format = "%s: %s\n", int $indent = 4): string
    {
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
    public static function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Format a boolean as "yes" or "no"
     */
    public static function yn(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    /**
     * Format a date and time without redundant information
     */
    public static function date(
        DateTimeInterface $date,
        string $before = '[',
        ?string $after = ']'
    ): string {
        $date = Date::maybeSetTimezone($date);

        $format = ['D j M'];
        // Add year if `$date` is not in the current year
        if (date('Y') !== $date->format('Y')) {
            $format[] = 'Y';
        }
        // Add time and timezone if the time is not midnight
        if ($date->format('H:i:s') !== '00:00:00') {
            $format[] = 'H:i:s T';
        }
        $format = implode(' ', $format);

        return Str::wrap($date->format($format), $before, $after);
    }

    /**
     * Format a date and time range without redundant information
     */
    public static function dateRange(
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $delimiter = '–',
        string $before = '[',
        ?string $after = ']'
    ): string {
        $from = Date::maybeSetTimezone($from);
        $to = Date::maybeSetTimezone($to);

        $sameTimezone = $from->getTimezone()->getName() === $to->getTimezone()->getName();
        $noTime = $sameTimezone &&
            $from->format('H:i:s') === '00:00:00' &&
            $to->format('H:i:s') === '00:00:00';

        $fromFormat = ['D j M'];
        $fromYear = $from->format('Y');
        // Add year if `$from` and `$to` are in different years or if they are
        // not in the current year
        if ($to->format('Y') !== $fromYear || date('Y') !== $fromYear) {
            $fromFormat[] = 'Y';
        }
        // Add time unless both times are midnight in the same timezone
        if (!$noTime) {
            $fromFormat[] = 'H:i:s';
        }
        $toFormat = $fromFormat;
        // Add timezone after `$to` if both times are in the same timezone,
        // otherwise add it after `$from` as well
        if (!$noTime) {
            $toFormat[] = 'T';
            if (!$sameTimezone) {
                $fromFormat[] = 'T';
            }
        }
        $fromFormat = implode(' ', $fromFormat);
        $toFormat = implode(' ', $toFormat);

        return sprintf(
            '%s%s%s',
            Str::wrap($from->format($fromFormat), $before, $after),
            $delimiter,
            Str::wrap($to->format($toFormat), $before, $after)
        );
    }

    /**
     * Format a size in bytes by rounding to an appropriate binary unit (B, KiB,
     * MiB, TiB, ...)
     */
    public static function bytes(int $bytes, int $precision = 0): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        $bytes = max(0, $bytes);
        $power = min(count($units) - 1, floor(($bytes ? log($bytes) : 0) / log(1024)));
        $power = max(0, $precision ? $power : $power - 1);

        return sprintf(
            $precision ? "%01.{$precision}f%s" : '%d%s',
            $bytes / pow(1024, $power),
            $units[$power]
        );
    }
}
