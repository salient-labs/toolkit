<?php declare(strict_types=1);

namespace Lkrms\Utility;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use UnexpectedValueException;

/**
 * Make data human-readable
 *
 */
final class Formatters
{
    /**
     * Format values in a list
     *
     * Non-scalar values are converted to JSON first.
     *
     * @param string $format The format to pass to `sprintf`. Must include a
     * string conversion specification (`%s`).
     * @param int $indentSpaces Spaces to add after newlines in `$list`.
     */
    public function list(array $list, string $format = "- %s\n", int $indentSpaces = 2): string
    {
        $indent = str_repeat(' ', $indentSpaces);
        $string = '';

        foreach ($list as $value) {
            if (!is_scalar($value)) {
                $value = json_encode($value);
            }
            $value = str_replace("\r\n", "\n", (string) $value);
            $value = str_replace("\n", PHP_EOL . $indent, $value, $count);

            $string .= sprintf($format, $value);
        }

        return $string;
    }

    /**
     * Format an array's keys and values
     *
     * Non-scalar values are converted to JSON first.
     *
     * @param string $format The format to pass to `sprintf`. Must include two
     * string conversion specifications (`%s`).
     * @param int $indentSpaces Spaces to add after newlines in `$array`.
     */
    public function array(array $array, string $format = "%s: %s\n", int $indentSpaces = 4): string
    {
        $indent = str_repeat(' ', $indentSpaces);
        $string = '';

        foreach ($array as $key => $value) {
            if (!is_scalar($value)) {
                $value = json_encode($value);
            }
            $value = str_replace("\r\n", "\n", (string) $value);
            $value = str_replace("\n", PHP_EOL . $indent, $value, $count);
            if ($count) {
                $value = PHP_EOL . $indent . $value;
            }

            $string .= sprintf($format, $key, $value);
        }

        return $string;
    }

    /**
     * "true" if a boolean is true, "false" if it's not
     *
     */
    public function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * "yes" if a boolean is true, "no" if it's not
     *
     */
    public function yn(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function getBetween(string $between): array
    {
        if (strlen($between) % 2) {
            throw new UnexpectedValueException('String length is not even: ' . $between);
        }

        return [
            substr($between, 0, strlen($between) / 2),
            substr($between, strlen($between) / 2)
        ];
    }

    private function maybeSetTimezone(DateTimeInterface $date): DateTimeInterface
    {
        if ($date->getOffset() || date_default_timezone_get() === 'UTC') {
            return $date;
        }

        return (
            $date instanceof DateTimeImmutable ? $date : DateTimeImmutable::createFromMutable($date)
        )->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    /**
     * Format a DateTime without redundant information
     *
     */
    public function date(DateTimeInterface $date, string $between = '[]'): string
    {
        [$date, $l, $r] = [
            $this->maybeSetTimezone($date),
            ...$this->getBetween($between),
        ];

        $noYear = date('Y') == $date->format('Y');
        $noTime = $date->format('H:i:s') == '00:00:00';

        $format = 'D j M' . ($noYear ? '' : ' Y') . ($noTime ? '' : ' H:i:s T');

        return $l . $date->format($format) . $r;
    }

    /**
     * Format a DateTime range without redundant information
     *
     */
    public function dateRange(
        DateTimeInterface $from,
        DateTimeInterface $to,
        string $between = '[]',
        string $delimiter = '–'
    ): string {
        [$from, $to, $l, $r] = [
            $this->maybeSetTimezone($from),
            $this->maybeSetTimezone($to),
            ...$this->getBetween($between),
        ];

        $sameYear = ($year = $from->format('Y')) == $to->format('Y');
        $sameZone = $from->getTimezone() == $to->getTimezone();
        $noYear = $sameYear && date('Y') == $year;
        $noTime = $sameZone && $from->format('H:i:s') == '00:00:00' && $to->format('H:i:s') == '00:00:00';

        $fromFormat = 'D j M' . ($noYear ? '' : ' Y') . ($noTime ? '' : ' H:i:s' . ($sameZone ? '' : ' T'));
        $toFormat = $fromFormat . ($noTime || !$sameZone ? '' : ' T');

        return $l . $from->format($fromFormat) . "$r$delimiter$l" . $to->format($toFormat) . $r;
    }

    /**
     * Round an integer to an appropriate binary unit (B, KiB, MiB, TiB, ...)
     *
     */
    public function bytes(int $bytes, int $precision = 0): string
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
