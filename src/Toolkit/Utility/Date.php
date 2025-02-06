<?php declare(strict_types=1);

namespace Salient\Utility;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Work with date and time values, timezones and intervals
 *
 * @api
 */
final class Date extends AbstractUtility
{
    /**
     * Get a DateTimeImmutable from a DateTimeInterface
     *
     * @param DateTimeInterface|null $datetime If `null`, the current time is
     * used.
     */
    public static function immutable(?DateTimeInterface $datetime = null): DateTimeImmutable
    {
        return $datetime instanceof DateTimeImmutable
            ? $datetime
            : ($datetime
                ? DateTimeImmutable::createFromMutable($datetime)
                : new DateTimeImmutable());
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
     * Set the timezone of a date and time if not already set
     *
     * @param DateTimeZone|string|null $timezone If `null`, the timezone
     * returned by {@see date_default_timezone_get()} is used.
     */
    public static function maybeSetTimezone(DateTimeInterface $datetime, $timezone = null): DateTimeImmutable
    {
        $datetime = self::immutable($datetime);
        $tz = $datetime->getTimezone()->getName();
        if (
            ($tz === 'UTC' || $tz === '+00:00')
            && ($timezone !== null || date_default_timezone_get() !== 'UTC')
        ) {
            $timezone = self::timezone($timezone);
            if ($tz !== $timezone->getName()) {
                return $datetime->setTimezone($timezone);
            }
        }
        return $datetime;
    }
}
