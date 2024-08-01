<?php declare(strict_types=1);

namespace Salient\Tests\Utility;

use Salient\Contract\Core\Jsonable;
use Salient\Tests\TestCase;
use Salient\Utility\File;
use Salient\Utility\Format;
use Salient\Utility\Json;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Stringable;

/**
 * @covers \Salient\Utility\Format
 */
final class FormatTest extends TestCase
{
    /**
     * @dataProvider listProvider
     *
     * @param mixed[]|null $list
     */
    public function testList(
        string $expected,
        ?array $list,
        string $format = "- %s\n",
        int $indent = 2
    ): void {
        $this->assertSame($expected, Format::list($list, $format, $indent));
    }

    /**
     * @return array<array{string,mixed[]|null,2?:string,3?:int}>
     */
    public static function listProvider(): array
    {
        return [
            ['', null],
            ['', []],
            [
                implode("\n", [
                    '- 0',
                    '- -2.1',
                    '- true',
                    '- false',
                    '- ',
                    '- ',
                    '- two',
                    '  lines',
                    '- []',
                    '- [3]',
                    '- {"foo":null}',
                    '',
                ]),
                [
                    0,
                    -2.1,
                    true,
                    false,
                    '',
                    null,
                    "two\nlines",
                    [],
                    [3],
                    ['foo' => null],
                ],
            ],
            [
                implode("\n", [
                    ' -> a',
                    ' -> b',
                    '    c',
                    ' -> d',
                    '',
                ]),
                [
                    'a',
                    "b\nc",
                    'd',
                ],
                " -> %s\n",
                4,
            ],
        ];
    }

    /**
     * @dataProvider arrayProvider
     *
     * @param mixed[]|null $array
     */
    public function testArray(
        string $expected,
        ?array $array,
        string $format = "%s: %s\n",
        int $indent = 4
    ): void {
        $this->assertSame($expected, Format::array($array, $format, $indent));
    }

    /**
     * @return array<array{string,mixed[]|null,2?:string,3?:int}>
     */
    public static function arrayProvider(): array
    {
        return [
            ['', null],
            ['', []],
            [
                implode("\n", [
                    '0: 0',
                    '1: -2.1',
                    'true: true',
                    'false: false',
                    'empty: ',
                    'null: ',
                    'split: ',
                    '    two',
                    '    lines',
                    '2: []',
                    '3: [3]',
                    '4: {"foo":null}',
                    '',
                ]),
                [
                    0,
                    -2.1,
                    'true' => true,
                    'false' => false,
                    'empty' => '',
                    'null' => null,
                    'split' => "two\nlines",
                    [],
                    [3],
                    ['foo' => null],
                ],
            ],
            [
                implode("\n", [
                    '1. a',
                    '2. b',
                    'c',
                    '3. d',
                    '',
                ]),
                [1 => 'a', "b\nc", 'd'],
                "%d. %s\n",
                0,
            ]
        ];
    }

    /**
     * @dataProvider valueProvider
     *
     * @param mixed $value
     */
    public function testValue(string $expected, $value): void
    {
        $this->assertSame($expected, Format::value($value));
    }

    /**
     * @return array<array{string,mixed}>
     */
    public static function valueProvider(): array
    {
        return [
            ['', null],
            ['0', 0],
            ['-2.1', -2.1],
            ['true', true],
            ['false', false],
            ['', ''],
            ["two\nlines", "two\r\nlines"],
            ['[]', []],
            ['[3]', [3]],
            ['{"foo":null}', ['foo' => null]],
            ['Hello, world!', new class implements Stringable {
                function __toString() { return 'Hello, world!'; }
            }],
            ['{"baz":71}', new class implements Jsonable {
                public function toJson(int $flags = 0): string
                {
                    return Json::stringify(['baz' => 71], $flags);
                }
            }],
            ['<resource (stream)>', File::open(__FILE__, 'r')],
            ['<array>', [File::open(__FILE__, 'r')]],
        ];
    }

    /**
     * @dataProvider boolProvider
     */
    public function testBool(string $expected, ?bool $value): void
    {
        $this->assertSame($expected, Format::bool($value));
    }

    /**
     * @return array<array{string,bool|null}>
     */
    public static function boolProvider(): array
    {
        return [
            ['', null],
            ['true', true],
            ['false', false],
        ];
    }

    /**
     * @dataProvider ynProvider
     */
    public function testYn(string $expected, ?bool $value): void
    {
        $this->assertSame($expected, Format::yn($value));
    }

    /**
     * @return array<array{string,bool|null}>
     */
    public static function ynProvider(): array
    {
        return [
            ['', null],
            ['yes', true],
            ['no', false],
        ];
    }

    /**
     * @dataProvider dateProvider
     */
    public function testDate(
        string $expected,
        ?DateTimeInterface $date,
        string $before = '[',
        ?string $after = ']',
        ?string $thisYear = null
    ): void {
        $this->assertSame(
            $expected,
            Format::date($date, $before, $after, $thisYear)
        );
    }

    /**
     * @return array<array{string,DateTimeInterface|null,2?:string,3?:string|null,4?:string|null}>
     */
    public static function dateProvider(): array
    {
        $date1 = new DateTimeImmutable('2021-10-02 17:23:14 AEST');
        $date2 = $date1->setTime(0, 0, 0);

        return [
            ['', null],
            ['[Sat 2 Oct 2021 17:23:14 AEST]', $date1],
            ['[Sat 2 Oct 17:23:14 AEST]', $date1, '[', ']', '2021'],
            ['[Sat 2 Oct 2021]', $date2],
            ['[Sat 2 Oct]', $date2, '[', ']', '2021'],
            ['|Sat 2 Oct|', $date2, '|', null, '2021'],
        ];
    }

    /**
     * @dataProvider dateRangeProvider
     */
    public function testDateRange(
        string $expected,
        ?DateTimeInterface $from,
        ?DateTimeInterface $to,
        string $delimiter = '–',
        string $before = '[',
        ?string $after = ']',
        ?string $thisYear = null
    ): void {
        $this->assertSame(
            $expected,
            Format::dateRange($from, $to, $delimiter, $before, $after, $thisYear)
        );
    }

    /**
     * @return array<array{string,DateTimeInterface|null,DateTimeInterface|null,3?:string,4?:string,5?:string|null,6?:string|null}>
     */
    public static function dateRangeProvider(): array
    {
        $tz = new DateTimeZone('Australia/Sydney');
        $from1 = new DateTimeImmutable('2021-10-02 17:23:14', $tz);
        $to1 = new DateTimeImmutable('2021-10-08 15:13:42', $tz);
        $next1 = new DateTimeImmutable('2022-10-01 17:23:14', $tz);
        $from2 = $from1->setTime(0, 0, 0);
        $to2 = $to1->setTime(0, 0, 0);
        $next2 = $next1->setTime(0, 0, 0);

        return [
            ['', null, null],
            ['[Sat 2 Oct 2021 17:23:14 AEST]–', $from1, null],
            ['–[Fri 8 Oct 2021 15:13:42 AEDT]', null, $to1],
            ['[Sat 2 Oct 17:23:14]–[Sat 2 Oct 2021 17:23:14 AEST]', $from1, $from1],
            ['[Sat 2 Oct 17:23:14 AEST]–[Fri 8 Oct 2021 15:13:42 AEDT]', $from1, $to1],
            ['[Sat 2 Oct 17:23:14 AEST]–', $from1, null, '–', '[', ']', '2021'],
            ['–[Fri 8 Oct 15:13:42 AEDT]', null, $to1, '–', '[', ']', '2021'],
            ['[Sat 2 Oct 17:23:14]–[Sat 2 Oct 17:23:14 AEST]', $from1, $from1, '–', '[', ']', '2021'],
            ['[Sat 2 Oct 17:23:14 AEST]–[Fri 8 Oct 15:13:42 AEDT]', $from1, $to1, '–', '[', ']', '2021'],
            ['[Sat 2 Oct 2021 17:23:14]–[Sat 1 Oct 2022 17:23:14 AEST]', $from1, $next1, '–', '[', ']', '2022'],
            ['[Sat 2 Oct 2021]–', $from2, null],
            ['–[Fri 8 Oct 2021]', null, $to2],
            ['[Sat 2 Oct]–[Sat 2 Oct 2021]', $from2, $from2],
            ['[Sat 2 Oct]–[Fri 8 Oct 2021]', $from2, $to2],
            ['[Sat 2 Oct] to ', $from2, null, ' to ', '[', ']', '2021'],
            [' to [Fri 8 Oct]', null, $to2, ' to ', '[', ']', '2021'],
            ['[Sat 2 Oct] to [Sat 2 Oct]', $from2, $from2, ' to ', '[', ']', '2021'],
            ['[Sat 2 Oct] to [Fri 8 Oct]', $from2, $to2, ' to ', '[', ']', '2021'],
            ['[Sat 2 Oct 2021]–[Sat 1 Oct 2022]', $from2, $next2, '–', '[', ']', '2022'],
            ['|Sat 2 Oct| to |Fri 8 Oct|', $from2, $to2, ' to ', '|', null, '2021'],
        ];
    }

    /**
     * @dataProvider bytesProvider
     */
    public function testBytes(
        string $expected,
        ?int $bytes,
        int $precision = 3,
        bool $binary = true
    ): void {
        $this->assertSame($expected, Format::bytes($bytes, $precision, $binary));
    }

    /**
     * @return array<array{string,int|null,2?:int,3?:bool}>
     */
    public static function bytesProvider(): array
    {
        return [
            // 0
            ['0B', 0], ['0B', 0, 0], ['0B', 0, 3, false], ['0B', 0, 0, false],
            // 1
            ['1B', 1], ['1B', 1, 0], ['1B', 1, 3, false], ['1B', 1, 0, false],
            // 999
            ['999B', 999], ['999B', 999, 0], ['999B', 999, 3, false], ['999B', 999, 0, false],
            // 1000
            ['0.976KiB', 1000], ['1000B', 1000, 0], ['1.000kB', 1000, 3, false], ['1kB', 1000, 0, false],
            // 1023
            ['0.999KiB', 1023], ['1023B', 1023, 0], ['1.022kB', 1023, 3, false], ['1kB', 1023, 0, false],
            // 1024
            ['1.000KiB', 1024], ['1KiB', 1024, 0], ['1.024kB', 1024, 3, false], ['1kB', 1024, 0, false],
            // 999999
            ['976.561KiB', 999999], ['976KiB', 999999, 0], ['999.999kB', 999999, 3, false], ['999kB', 999999, 0, false],
            // 1000000
            ['976.562KiB', 1000000], ['976KiB', 1000000, 0], ['1.000MB', 1000000, 3, false], ['1MB', 1000000, 0, false],
            // 1048575
            ['0.999MiB', 1048575], ['1023KiB', 1048575, 0], ['1.048MB', 1048575, 3, false], ['1MB', 1048575, 0, false],
            // 1048576
            ['1.000MiB', 1048576], ['1MiB', 1048576, 0], ['1.048MB', 1048576, 3, false], ['1MB', 1048576, 0, false],
            // 999999999
            ['953.674MiB', 999999999], ['953MiB', 999999999, 0], ['999.999MB', 999999999, 3, false], ['999MB', 999999999, 0, false],
            // 1000000000
            ['953.674MiB', 1000000000], ['953MiB', 1000000000, 0], ['1.000GB', 1000000000, 3, false], ['1GB', 1000000000, 0, false],
            // 1073741823
            ['0.999GiB', 1073741823], ['1023MiB', 1073741823, 0], ['1.073GB', 1073741823, 3, false], ['1GB', 1073741823, 0, false],
            // 1073741824
            ['1.000GiB', 1073741824], ['1GiB', 1073741824, 0], ['1.073GB', 1073741824, 3, false], ['1GB', 1073741824, 0, false],
            //
            ['', null]
        ];
    }

    /**
     * @dataProvider invalidBytesProvider
     */
    public function testInvalidBytes(
        string $expectedMessage,
        ?int $bytes,
        int $precision = 3,
        bool $binary = true
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        Format::bytes($bytes, $precision, $binary);
    }

    /**
     * @return array<array{string,int|null,2?:int,3?:bool}>
     */
    public static function invalidBytesProvider(): array
    {
        return [
            ['$bytes cannot be less than zero', -1],
            ['$precision cannot be less than zero', 0, -1],
        ];
    }
}
