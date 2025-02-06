<?php declare(strict_types=1);

namespace Salient\Tests\Core\Date;

use Salient\Core\Date\DateFormatter;
use Salient\Tests\TestCase;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * @covers \Salient\Core\Date\DateFormatter
 */
final class DateFormatterTest extends TestCase
{
    /**
     * @dataProvider formatProvider
     */
    public function testFormat(string $expected, string $tzExpected, DateTimeInterface $date): void
    {
        $tz = new DateTimeZone('Australia/Sydney');
        $formatter = new DateFormatter(DateTimeInterface::RFC3339_EXTENDED);
        $tzFormatter = new DateFormatter(DateTimeInterface::RFC3339_EXTENDED, $tz);
        $this->assertSame($expected, $formatter->format($date));
        $this->assertSame($tzExpected, $tzFormatter->format($date));
    }

    /**
     * @return array<array{string,string,DateTimeInterface}>
     */
    public static function formatProvider(): array
    {
        return [
            ['2018-06-28T05:30:00.000+05:30', '2018-06-28T10:00:00.000+10:00', new DateTimeImmutable('2018-06-28T05:30:00.000+05:30')],
            ['2020-10-20T00:00:00.000+00:00', '2020-10-20T11:00:00.000+11:00', new DateTimeImmutable('2020-10-20T00:00:00.000+00:00')],
            ['2022-11-11T16:12:49.876+11:00', '2022-11-11T16:12:49.876+11:00', new DateTimeImmutable('2022-11-11T16:12:49.876+11:00')],
        ];
    }
}
