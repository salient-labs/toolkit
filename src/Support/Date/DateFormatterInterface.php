<?php declare(strict_types=1);

namespace Lkrms\Contract;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Formats and parses date and time values
 */
interface IDateFormatter
{
    /**
     * Format a date and time
     */
    public function format(DateTimeInterface $date): string;

    /**
     * Convert a string to a date and time, if possible
     */
    public function parse(string $value): ?DateTimeImmutable;
}
