<?php declare(strict_types=1);

namespace Lkrms\Support\Date;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Parses date and time strings with a given format
 *
 * @api
 */
final class DateFormatParser implements DateParserInterface
{
    private string $Format;

    /**
     * Creates a new DateFormatParser object
     *
     * @see DateTimeImmutable::createFromFormat()
     */
    public function __construct(string $format)
    {
        // Reset fields that don't appear in the format string to zero-like
        // values, otherwise they will be set to the current date and time
        if (strpos($format, '!') === false && strpos($format, '|') === false) {
            $format .= '|';
        }

        $this->Format = $format;
    }

    /**
     * @inheritDoc
     */
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat($this->Format, $value, $timezone);
        if ($date === false) {
            return null;
        }
        return $date;
    }
}
