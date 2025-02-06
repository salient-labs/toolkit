<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\DateParser;
use Salient\Tests\TestCase;
use DateTimeInterface;
use DateTimeZone;

/**
 * @covers \Salient\Core\DateParser
 */
final class DateParserTest extends TestCase
{
    /**
     * @dataProvider parseProvider
     */
    public function testParse(?string $expected, string $value, ?DateTimeZone $timezone = null): void
    {
        $parser = new DateParser();
        $actual = $parser->parse($value, $timezone);
        if ($expected === null) {
            $this->assertNull($actual);
            return;
        }
        $this->assertNotNull($actual);
        $this->assertSame($expected, $actual->format(DateTimeInterface::RFC3339_EXTENDED));
    }

    /**
     * @return array<array{string|null,string,2?:DateTimeZone|null}>
     */
    public static function parseProvider(): array
    {
        $tz = new DateTimeZone('Australia/Sydney');

        return [
            [null, ''],
            ['2018-06-28T00:00:00.000+00:00', '23 June 2018 +5 day'],
            ['2018-06-28T00:00:00.000+10:00', '23 June 2018 +5 day', $tz],
            ['2018-06-28T05:42:49.876+05:30', '2018-06-28 05:42:49.876 +05:30'],
            ['2018-06-28T05:42:49.876+05:30', '2018-06-28 05:42:49.876 +05:30', $tz],
            ['2022-11-11T05:12:49.876+00:00', '@1668143569.876000'],
            ['2022-11-11T16:12:49.876+11:00', '@1668143569.876000', $tz],
        ];
    }
}
