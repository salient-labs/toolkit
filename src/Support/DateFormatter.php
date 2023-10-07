<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TFullyReadable;
use Lkrms\Contract\IDateFormatter;
use Lkrms\Contract\IDateParser;
use Lkrms\Contract\IImmutable;
use Lkrms\Contract\IReadable;
use Lkrms\Support\DateParser\CreateFromFormatDateParser;
use Lkrms\Utility\Convert;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * An immutable date formatter and parser that optionally applies a preferred
 * timezone to both operations
 *
 * @property-read string $Format
 * @property-read DateTimeZone|null $Timezone
 * @property-read IDateParser[] $Parsers
 */
final class DateFormatter implements IDateFormatter, IImmutable, IReadable
{
    use TFullyReadable;

    /**
     * @var string
     */
    protected $Format;

    /**
     * @var DateTimeZone|null
     */
    protected $Timezone;

    /**
     * @var IDateParser[]
     */
    protected $Parsers;

    /**
     * @param DateTimeZone|string|null $timezone
     */
    public function __construct(string $format = DateTimeInterface::ATOM, $timezone = null, IDateParser ...$parsers)
    {
        $this->Format = $format;
        $this->Timezone = is_null($timezone) ? null : Convert::toTimezone($timezone);
        $this->Parsers = $parsers ?: [new CreateFromFormatDateParser($format)];
    }

    /**
     * Format a date after optionally applying the preferred timezone
     *
     * If `$date` is a `DateTime` object, {@see DateFormatter::$Timezone} is
     * set, and `$date->Timezone` has a different value, `$date` is converted to
     * a `DateTimeImmutable` before applying the timezone. The original
     * `DateTime` object is not modified.
     */
    public function format(DateTimeInterface $date): string
    {
        if ($this->Timezone &&
                $this->Timezone->getName() !== $date->getTimezone()->getName()) {
            $date = Convert::toDateTimeImmutable($date)->setTimezone($this->Timezone);
        }

        return $date->format($this->Format);
    }

    /**
     * Convert a string to a date, if possible
     *
     * @return DateTimeImmutable|null a `DateTimeImmutable` object on success or
     * `null` on failure.
     */
    public function parse(string $value): ?DateTimeImmutable
    {
        foreach ($this->Parsers as $parser) {
            if ($date = $parser->parse($value, $this->Timezone)) {
                return $date;
            }
        }

        return null;
    }
}
