<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Work with dates and times
 *
 * @api
 */
final class Date extends AbstractUtility
{
    /**
     * Get a DateTimeImmutable from a DateTimeInterface
     *
     * A shim for {@see DateTimeImmutable::createFromInterface()}.
     */
    public static function immutable(DateTimeInterface $datetime): DateTimeImmutable
    {
        return $datetime instanceof DateTimeImmutable
            ? $datetime
            : DateTimeImmutable::createFromMutable($datetime);
    }

    /**
     * Get a DateTimeZone from a string or DateTimeZone
     *
     * @param DateTimeZone|string|null $timezone If `null`, the timezone
     * returned by {@see date_default_timezone_get()} is used.
     */
    public static function timezone($timezone = null): DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }
        if ($timezone === null) {
            $timezone = date_default_timezone_get();
        }
        return new DateTimeZone($timezone);
    }

    /**
     * Get a DateInterval or ISO-8601 duration in seconds
     *
     * @param DateInterval|string $interval
     */
    public static function duration($interval): int
    {
        if (!$interval instanceof DateInterval) {
            if (
                \PHP_VERSION_ID < 80000
                && Regex::match('/W.+D/', $interval)
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid $interval: %s',
                    $interval,
                ));
            }
            $interval = new DateInterval($interval);
        }

        $then = new DateTimeImmutable();
        $now = $then->add($interval);

        return $now->getTimestamp() - $then->getTimestamp();
    }

    /**
     * Set the timezone of a DateTimeInterface if it doesn't already have one
     *
     * @param DateTimeZone|string|null $timezone If `null`, the timezone
     * returned by {@see date_default_timezone_get()} is used.
     */
    public static function maybeSetTimezone(DateTimeInterface $datetime, $timezone = null): DateTimeImmutable
    {
        if (
            $datetime->getTimezone()->getName() !== 'UTC'
            || ($timezone === null && date_default_timezone_get() === 'UTC')
        ) {
            return self::immutable($datetime);
        }

        $timezone = self::timezone($timezone);
        if ($datetime->getTimezone()->getName() === $timezone->getName()) {
            return self::immutable($datetime);
        }

        return self::immutable($datetime)->setTimezone($timezone);
    }
}
