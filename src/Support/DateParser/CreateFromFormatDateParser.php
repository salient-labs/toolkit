<?php

declare(strict_types=1);

namespace Lkrms\Support\DateParser;

use DateTimeImmutable;
use DateTimeZone;
use Lkrms\Contract\IDateParser;

/**
 * A wrapper around DateTimeImmutable::createFromFormat()
 *
 */
final class CreateFromFormatDateParser implements IDateParser
{
    /**
     * @var string
     */
    private $Format;

    public function __construct(string $format)
    {
        // Reset fields that don't appear in the format string to zero,
        // otherwise they default to the current time
        if (strpos($format, "!") === false && strpos($format, "|") === false)
        {
            $format .= "|";
        }

        $this->Format = $format;
    }

    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat($this->Format, $value, $timezone) ?: null;
    }
}
