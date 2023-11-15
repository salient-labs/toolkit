<?php declare(strict_types=1);

namespace Lkrms\Utility;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Manipulate dates and times
 */
final class Date
{
    /**
     * Get a DateTimeImmutable from a DateTime or DateTimeImmutable
     *
     * A shim for {@see DateTimeImmutable::createFromInterface()}.
     */
    public static function immutable(DateTimeInterface $datetime): DateTimeImmutable
    {
        return $datetime instanceof DateTimeImmutable
            ? $datetime
            : DateTimeImmutable::createFromMutable($datetime);
    }
}
