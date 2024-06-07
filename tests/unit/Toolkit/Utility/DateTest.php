<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Core\Utility\Date;
use Salient\Tests\TestCase;
use DateInterval;
use DateMalformedIntervalStringException;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * @covers \Salient\Core\Utility\Date
 */
final class DateTest extends TestCase
{
    public function testImmutable(): void
    {
        $immutable = new DateTimeImmutable();
        $notImmutable = new DateTime();
        $this->assertSame($immutable, Date::immutable($immutable));
        $this->assertNotSame($notImmutable, $immutable2 = Date::immutable($notImmutable));
        $this->assertInstanceOf(DateTimeImmutable::class, $immutable2);
        $this->assertSame($notImmutable->getTimestamp(), $immutable2->getTimestamp());
    }

    /**
     * @dataProvider timezoneProvider
     *
     * @param DateTimeZone|string|null $timezone
     */
    public function testTimezone(DateTimeZone $expected, $timezone = null): void
    {
        $actual = Date::timezone($timezone);
        if ($timezone instanceof DateTimeZone) {
            $this->assertSame($expected, $actual);
        } else {
            $this->assertSame($expected->getName(), $actual->getName());
        }
    }

    /**
     * @return array<array{DateTimeZone,1?:DateTimeZone|string|null}>
     */
    public static function timezoneProvider(): array
    {
        $tz = new DateTimeZone('Australia/Sydney');

        return [
            [new DateTimeZone(date_default_timezone_get())],
            [new DateTimeZone('UTC'), 'UTC'],
            [new DateTimeZone('GMT+11:00'), '+11:00'],
            [$tz, $tz],
        ];
    }

    /**
     * @dataProvider durationProvider
     *
     * @param int|string $expected
     * @param DateInterval|string $interval
     */
    public function testDuration($expected, $interval): void
    {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Date::duration($interval));
    }

    /**
     * @return array<array{int|string,DateInterval|string}>
     */
    public static function durationProvider(): array
    {
        $exception = \PHP_VERSION_ID < 80300
            ? Exception::class
            : DateMalformedIntervalStringException::class;

        return [
            [0, 'P0D'],
            [0, 'PT0S'],
            [\PHP_VERSION_ID < 80000
                ? InvalidArgumentException::class . ',Invalid $interval: P1W2D'
                : 9 * 24 * 3600, 'P1W2D'],
            [9 * 24 * 3600, 'P9D'],
            [48 * 60, 'PT48M'],
            [$exception, 'P1WD'],
            [$exception, 'P2D1W'],
            [$exception, 'pt48m'],
        ];
    }

    /**
     * @runInSeparateProcess
     */
    public function testMaybeSetTimezone(): void
    {
        date_default_timezone_set('America/Los_Angeles');

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $timestamp = $now->getTimestamp();
        $this->assertSame('UTC', $now->format('e'));
        $this->assertSame('America/Los_Angeles', ($then = Date::maybeSetTimezone($now))->format('e'));
        $this->assertInstanceOf(DateTimeImmutable::class, $then);
        $this->assertSame($timestamp, $then->getTimestamp());

        $this->assertSame('Australia/Sydney', ($then = Date::maybeSetTimezone($now, 'Australia/Sydney'))->format('e'));
        $this->assertSame($timestamp, $then->getTimestamp());

        $now = new DateTime('now', new DateTimeZone('Australia/Sydney'));
        $this->assertSame('Australia/Sydney', $now->format('e'));
        $this->assertSame('Australia/Sydney', Date::maybeSetTimezone($now)->format('e'));

        $now = new DateTimeImmutable();
        $this->assertSame($now, Date::maybeSetTimezone($now));
    }
}
