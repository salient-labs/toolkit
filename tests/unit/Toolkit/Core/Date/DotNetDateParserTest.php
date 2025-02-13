<?php declare(strict_types=1);

namespace Salient\Tests\Core\Date;

use Salient\Core\Date\DotNetDateParser;
use Salient\Tests\TestCase;
use DateTimeInterface;
use DateTimeZone;

/**
 * @covers \Salient\Core\Date\DotNetDateParser
 */
final class DotNetDateParserTest extends TestCase
{
    /**
     * @dataProvider parseProvider
     */
    public function testParse(?string $expected, string $value, ?DateTimeZone $timezone = null): void
    {
        $parser = new DotNetDateParser();
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
            [null, '/Date()/'],
            [null, '/Date(0+00:00)/'],
            [null, "/Date(0)/\n"],
            ['1970-01-01T00:00:00.000+00:00', '/Date(0)/'],
            ['1970-01-01T10:00:00.000+10:00', '/Date(0)/', $tz],
            ['1970-01-01T00:00:00.000+00:00', '/Date(0+0000)/'],
            ['1970-01-01T10:00:00.000+10:00', '/Date(0+0000)/', $tz],
            ['2020-10-20T00:00:00.000+00:00', '/Date(1603152000000)/'],
            ['2020-10-20T11:00:00.000+11:00', '/Date(1603152000000)/', $tz],
            ['2018-06-28T05:30:00.000+05:30', '/Date(1530144000000+0530)/'],
            ['2018-06-28T05:30:00.000+05:30', '/Date(1530144000000+0530)/', $tz],
            ['2022-11-11T05:12:49.876+00:00', '/Date(1668143569876)/'],
            ['2022-11-11T16:12:49.876+11:00', '/Date(1668143569876)/', $tz],
            ['2022-11-11T16:12:49.876+11:00', '/Date(1668143569876+1100)/'],
            ['2022-11-11T16:12:49.876+11:00', '/Date(1668143569876+1100)/', $tz],
        ];
    }
}
