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
     * Convert a value to a date and time, or return null if it can't be parsed
     */
    public function parse(string $value): ?DateTimeImmutable;
}
