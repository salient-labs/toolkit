<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Tests\TestCase;
use Salient\Core\Utility\Date;
use DateInterval;
use DateMalformedIntervalStringException;
use Exception;

/**
 * @covers \Salient\Core\Utility\Date
 */
final class DateTest extends TestCase
{
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
     * @return array<array{int,DateInterval|string}>
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
}
