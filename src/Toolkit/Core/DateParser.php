<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\DateParserInterface;
use Salient\Utility\Test;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Parses date and time strings understood by strtotime()
 *
 * @api
 */
final class DateParser implements DateParserInterface
{
    /**
     * @inheritDoc
     */
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        if (!Test::isDateString($value)) {
            return null;
        }
        $date = new DateTimeImmutable($value, $timezone);
        if ($timezone) {
            return $date->setTimezone($timezone);
        }
        return $date;
    }
}
