<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\DateParserInterface;
use Salient\Core\Utility\Date;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Formats and parses dates, optionally applying a preferred timezone to both
 * operations
 *
 * @api
 */
final class DateFormatter implements DateFormatterInterface
{
    private string $Format;

    private ?DateTimeZone $Timezone;

    /**
     * @var DateParserInterface[]
     */
    private array $Parsers;

    /**
     * @param DateTimeZone|string|null $timezone
     */
    public function __construct(string $format = DateTimeInterface::ATOM, $timezone = null, DateParserInterface ...$parsers)
    {
        $this->Format = $format;
        $this->Timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;
        $this->Parsers = $parsers ?: [new DateFormatParser($format)];
    }

    /**
     * @inheritDoc
     */
    public function format(DateTimeInterface $date): string
    {
        if (
            $this->Timezone
            && $this->Timezone->getName() !== $date->getTimezone()->getName()
        ) {
            $date = Date::immutable($date)->setTimezone($this->Timezone);
        }

        return $date->format($this->Format);
    }

    /**
     * @inheritDoc
     */
    public function parse(string $value): ?DateTimeImmutable
    {
        foreach ($this->Parsers as $parser) {
            $date = $parser->parse($value, $this->Timezone);
            if ($date) {
                return $date;
            }
        }

        return null;
    }
}
