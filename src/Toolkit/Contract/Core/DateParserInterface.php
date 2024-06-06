<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * @api
 */
interface DateParserInterface
{
    /**
     * Convert a value to a date and time, or return null if it can't be parsed
     *
     * If the value does not specify a timezone, one of the following is used
     * during parsing:
     *
     * - `$timezone` (if given)
     * - the parser's default timezone (if applicable)
     * - the script's default timezone (if set)
     * - `UTC`
     *
     * If `$timezone` is given, it is applied to the date and time before it is
     * returned.
     */
    public function parse(
        string $value,
        ?DateTimeZone $timezone = null
    ): ?DateTimeImmutable;
}
