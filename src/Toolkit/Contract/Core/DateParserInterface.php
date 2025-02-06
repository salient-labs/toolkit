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
     * Convert a string to a date and time, returning null if it can't be parsed
     *
     * The timezone used during parsing is the first that applies of:
     *
     * - the timezone specified by `$value`
     * - `$timezone`
     * - the parser's default timezone (if implemented)
     * - the default timezone used by date and time functions (if set)
     * - `UTC`
     */
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable;
}
