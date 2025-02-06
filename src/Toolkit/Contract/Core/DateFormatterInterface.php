<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use DateTimeInterface;

/**
 * @api
 */
interface DateFormatterInterface extends DateParserInterface
{
    /**
     * Format a date and time as a string
     */
    public function format(DateTimeInterface $date): string;
}
