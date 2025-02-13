<?php declare(strict_types=1);

namespace Salient\Core\Date;

use Salient\Contract\Core\DateParserInterface;
use Salient\Utility\Date;
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
        return Test::isDateString($value)
            ? Date::maybeSetTimezone(
                new DateTimeImmutable($value, $timezone),
                $timezone,
            )
            : null;
    }
}
