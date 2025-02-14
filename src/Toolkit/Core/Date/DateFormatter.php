<?php declare(strict_types=1);

namespace Salient\Core\Date;

use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\DateParserInterface;
use Salient\Utility\Date;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * @api
 */
final class DateFormatter implements DateFormatterInterface
{
    private string $Format;
    private ?DateTimeZone $Timezone;
    /** @var non-empty-array<DateParserInterface> */
    private array $Parsers;
    private string $TimezoneName;

    /**
     * @api
     *
     * @param string $format Passed to {@see DateTimeInterface::format()}. See
     * {@link https://www.php.net/manual/en/datetime.format.php} for syntax.
     * @param DateTimeZone|string|null $timezone Applied before formatting.
     * {@see DateTime} objects are not modified.
     * @param DateParserInterface ...$parsers {@see parse()} tries each of the
     * given parsers in turn and returns the first result that is not `null`.
     *
     * If no parsers are given, a {@see DateFormatParser}, created with the same
     * `$format`, is used.
     */
    public function __construct(
        string $format = DateTimeInterface::ATOM,
        $timezone = null,
        DateParserInterface ...$parsers
    ) {
        $this->Format = $format;
        $this->Timezone = is_string($timezone)
            ? new DateTimeZone($timezone)
            : $timezone;
        $this->Parsers = $parsers
            ? $parsers
            : [new DateFormatParser($format)];

        if ($this->Timezone) {
            $this->TimezoneName = $this->Timezone->getName();
        }
    }

    /**
     * @inheritDoc
     */
    public function format(DateTimeInterface $date): string
    {
        if (
            $this->Timezone
            && $this->TimezoneName !== $date->getTimezone()->getName()
        ) {
            $date = Date::immutable($date)->setTimezone($this->Timezone);
        }
        return $date->format($this->Format);
    }

    /**
     * @inheritDoc
     */
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        $timezone ??= $this->Timezone;
        foreach ($this->Parsers as $parser) {
            if ($date = $parser->parse($value, $timezone)) {
                return $date;
            }
        }
        return null;
    }
}
