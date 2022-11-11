<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts date strings to DateTimeImmutable objects
 *
 */
interface IDateParser
{
    /**
     * Convert a string to a date, if possible
     *
     * @return DateTimeImmutable|null a `DateTimeImmutable` object on success or
     * `null` on failure.
     */
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable;

}
