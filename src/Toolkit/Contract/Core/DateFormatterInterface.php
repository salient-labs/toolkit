<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * @api
 */
interface DateFormatterInterface
{
    /**
     * Format a date and time
     */
    public function format(DateTimeInterface $date): string;

    /**
     * Convert a string to a date and time, if possible
     *
     * Returns `null` if `$value` cannot be parsed.
     */
    public function parse(string $value): ?DateTimeImmutable;
}
