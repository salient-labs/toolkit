<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\DateParserInterface;
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
     * @param string $format Passed to
     * {@see DateTimeImmutable::createFromFormat()}. See
     * {@link https://www.php.net/manual/en/datetimeimmutable.createfromformat.php}
     * for syntax.
     */
    public function __construct(string $format)
    {
        // Reset fields that don't appear in the format string to zero-like
        // values, otherwise they will be set to the current date and time
        if (strcspn($format, '!|') === strlen($format)) {
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
