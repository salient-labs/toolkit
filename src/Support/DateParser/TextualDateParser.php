<?php declare(strict_types=1);

namespace Lkrms\Support\DateParser;

use Lkrms\Contract\IDateParser;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A wrapper around date_create_immutable()
 */
final class TextualDateParser implements IDateParser
{
    public function parse(string $value, ?DateTimeZone $timezone = null): ?DateTimeImmutable
    {
        return date_create_immutable($value, $timezone) ?: null;
    }
}
