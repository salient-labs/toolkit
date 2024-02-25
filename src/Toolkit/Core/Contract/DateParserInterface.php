<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use DateTimeImmutable;
use DateTimeZone;

/**
 * @api
 */
interface DateParserInterface
{
    /**
     * Convert a string to a date and time, if possible
     *
     * Returns `null` if `$value` cannot be parsed.
     *
     * @param DateTimeZone|null $timezone Applied to the date and time if given,
     * otherwise timezone information in `$value` should be used if present, or
     * a default timezone may be used.
     */
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable;
}
