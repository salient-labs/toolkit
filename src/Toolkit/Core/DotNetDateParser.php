<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\DateParserInterface;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Parses Microsoft JSON and legacy JSON.NET timestamps
 *
 * @link https://www.newtonsoft.com/json/help/html/datesinjson.htm
 *
 * @api
 */
final class DotNetDateParser implements DateParserInterface
{
    private const REGEX = '/^\/Date\((?<seconds>[0-9]*?)(?<milliseconds>[0-9]{1,3})(?<offset>[-+][0-9]{4})?\)\/$/';

    /**
     * @inheritDoc
     */
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        if (!Regex::match(self::REGEX, $value, $matches, \PREG_UNMATCHED_AS_NULL)) {
            return null;
        }

        $date = new DateTimeImmutable(sprintf(
            // PHP 7.4 requires 6 digits after the decimal point
            '@%d.%03d000',
            (int) Str::coalesce($matches['seconds'], '0'),
            (int) $matches['milliseconds'],
        ));
        if ($timezone) {
            return $date->setTimezone($timezone);
        }
        if ($matches['offset'] !== null) {
            return $date->setTimezone(new DateTimeZone($matches['offset']));
        }
        return $date;
    }
}
