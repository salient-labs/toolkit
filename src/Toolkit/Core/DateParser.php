<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Contract\DateParserInterface;
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
        $date = date_create_immutable($value, $timezone);
        if ($date === false) {
            return null;
        }
        return $date;
    }
}
