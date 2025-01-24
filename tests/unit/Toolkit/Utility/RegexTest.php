<?php declare(strict_types=1);

namespace Salient\Tests\Utility;

use Salient\Tests\TestCase;
use Salient\Utility\Exception\PcreErrorException;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Error;
use Stringable;

/**
 * @covers \Salient\Utility\Regex
 * @covers \Salient\Utility\Exception\PcreErrorException
 */
final class RegexTest extends TestCase
{
    public function testGrepFails(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_grep() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Regex::grep('/(?:\D+|<\d+>)*[!?]/', ['foobar foobar foobar']);
    }

    /**
     * @dataProvider grepProvider
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param array<TKey,TValue>|string $expected
     * @param array<TKey,TValue> $array
     */
    public function testGrep($expected, string $pattern, array $array, int $flags = 0): void
    {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Regex::grep($pattern, $array, $flags));
    }

    /**
     * @return array<array{mixed[]|string,string,mixed[],3?:int}>
     */
    public static function grepProvider(): array
    {
        $obj = new class implements Stringable {
            public function __toString()
            {
                return 'foobar';
            }
        };

        return [
            [
                [
                    0,
                    3.14,
                    true,
                    'string' => 'foobar',
                    Stringable::class => $obj,
                ],
                '/./',
                [
                    0,
                    3.14,
                    'null' => null,
                    true,
                    'false' => false,
                    'string' => 'foobar',
                    '',
                    Stringable::class => $obj,
                ],
            ],
            [
                [
                    'null' => null,
                    'false' => false,
                    3 => '',
                ],
                '/./',
                [
                    0,
                    3.14,
                    'null' => null,
                    true,
                    'false' => false,
                    'string' => 'foobar',
                    '',
                    Stringable::class => $obj,
                ],
                \PREG_GREP_INVERT,
            ],
            [
                Error::class,
                '/./',
                [
                    new class {},
                ],
            ],
        ];
    }

    public function testMatchFails(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_match() failed with PREG_BACKTRACK_LIMIT_ERROR');
        // This was taken from PHP's manual
        Regex::match('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
    }

    public function testMatchException(): void
    {
        try {
            Regex::match('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
        } catch (PcreErrorException $e) {
            $this->assertStringEndsWith(
                "\n\nPattern:\n/(?:\D+|<\d+>)*[!?]/\n\nSubject:\nfoobar foobar foobar",
                (string) $e,
            );
        }
    }

    public function testMatchAllFails(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_match_all() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Regex::matchAll('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
    }

    public function testReplaceFails(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Regex::replace('/(?:\D+|<\d+>)*[!?]/', '', 'foobar foobar foobar');
    }

    public function testReplaceException(): void
    {
        try {
            Regex::replace(['/(?:\D+|<\d+>)*[!?]/', '/\h+/'], ['', ' '], 'foobar foobar foobar');
        } catch (PcreErrorException $e) {
            $this->assertStringEndsWith(
                "\n\nPattern:\n- /(?:\D+|<\d+>)*[!?]/\n- /\h+/\n\nSubject:\nfoobar foobar foobar",
                (string) $e,
            );
        }
    }

    public function testReplaceCallbackFails(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace_callback() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Regex::replaceCallback('/(?:\D+|<\d+>)*[!?]/', fn() => '', 'foobar foobar foobar');
    }

    public function testReplaceCallbackArrayFails(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace_callback_array() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Regex::replaceCallbackArray(['/(?:\D+|<\d+>)*[!?]/' => fn() => ''], 'foobar foobar foobar');
    }

    public function testReplaceCallbackArrayException(): void
    {
        try {
            Regex::replaceCallbackArray(['/(?:\D+|<\d+>)*[!?]/' => fn() => ''], 'foobar foobar foobar');
        } catch (PcreErrorException $e) {
            $this->assertStringEndsWith(
                "\n\nPattern:\n/(?:\D+|<\d+>)*[!?]/: <callable>\n\nSubject:\nfoobar foobar foobar",
                (string) $e,
            );
        }
    }

    public function testSplitFails(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_split() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Regex::split('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
    }

    /**
     * @dataProvider quoteCharacterClassProvider
     */
    public function testQuoteCharacterClass(string $expected, string $characters, ?string $delimiter = null): void
    {
        $this->assertSame($expected, Regex::quoteCharacterClass($characters, $delimiter));
    }

    /**
     * @return array<array{string,string,2?:string|null}>
     */
    public static function quoteCharacterClassProvider(): array
    {
        return [
            ['\\\\', '\\'],
            ['\-', '-'],
            ['\^', '^'],
            ['\]', ']'],
            ['[', '['],
            ['|', '|'],
            ['/', '/'],
            ['/', '/', ''],
            ['\/', '/', '/'],
            ['\]\-\^\\\\@\/', ']-^\@/', '/'],
            ['\]\-\^\\\\\\@/', ']-^\@/', '@'],
            ['a\-z0\-9', 'a-z0-9'],
        ];
    }

    public function testInvisibleChar(): void
    {
        $dir = self::getPackagePath() . '/tests/data/unicode/ucd';
        $data = [
            "$dir/DerivedCoreProperties.txt" => 'Default_Ignorable_Code_Point',
            "$dir/extracted/DerivedGeneralCategory.txt" => 'Zs',
        ];

        $codepoints = [];
        foreach ($data as $file => $property) {
            $stream = File::open($file, 'r');
            while (($line = File::readLine($stream, $file)) !== '') {
                $row = trim(explode('#', $line, 2)[0]);
                if ($row === '') {
                    continue;
                }
                $fields = Str::split(';', $row, null, false);
                if (($fields[1] ?? null) !== $property || !Regex::match(
                    '/^([0-9a-f]{4,6})(?:\.\.([0-9a-f]{4,6}))?$/Di',
                    $fields[0],
                    $matches,
                )) {
                    continue;
                }
                $start = (int) hexdec($matches[1]);
                $end = (int) hexdec($matches[2] ?? $matches[1]);
                $range = range($start, $end);
                $codepoints += Arr::combine($range, $range);
            }
            File::close($stream, $file);
        }
        unset($codepoints[0x20]);
        ksort($codepoints, \SORT_NUMERIC);

        $this->assertNotEmpty($codepoints);

        $current = null;
        $ranges = [];
        foreach ($codepoints as $codepoint) {
            if ($current && $codepoint - $current[1] === 1) {
                $current[1] = $codepoint;
                continue;
            }
            if ($current) {
                $ranges[] = $current;
            }
            $current = [$codepoint, $codepoint];
        }
        $ranges[] = $current;

        $regex = '';
        foreach ($ranges as [$start, $end]) {
            switch ($end - $start) {
                case 0:
                    $regex .= sprintf('\x{%04X}', $start);
                    break;
                case 1:
                    $regex .= sprintf('\x{%04X}\x{%04X}', $start, $end);
                    break;
                default:
                    $regex .= sprintf('\x{%04X}-\x{%04X}', $start, $end);
                    break;
            }
        }

        $this->assertSame("[$regex]", Regex::INVISIBLE_CHAR);
    }
}
