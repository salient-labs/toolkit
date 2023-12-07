<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Exception\PcreErrorException;
use Lkrms\Utility\Pcre;

final class PcreTest extends \Lkrms\Tests\TestCase
{
    public function testGrepFails(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_grep() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::grep('/(?:\D+|<\d+>)*[!?]/', ['foobar foobar foobar']);
    }

    /**
     * @dataProvider grepProvider
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|\Stringable|null
     *
     * @param array<TKey,TValue>|string $expected
     * @param array<TKey,TValue> $array
     */
    public function testGrep($expected, string $pattern, array $array, int $flags = 0): void
    {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Pcre::grep($pattern, $array, $flags));
    }

    /**
     * @return array<array{mixed[]|string,string,mixed[],3?:int}>
     */
    public static function grepProvider(): array
    {
        $obj = new class implements \Stringable {
            public function __toString(): string
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
                    \Stringable::class => $obj,
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
                    \Stringable::class => $obj,
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
                    \Stringable::class => $obj,
                ],
                PREG_GREP_INVERT,
            ],
            [
                \Error::class,
                '/./',
                [
                    new class {},
                ],
            ],
        ];
    }

    public function testMatch(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_match() failed with PREG_BACKTRACK_LIMIT_ERROR');
        // This was taken from PHP's manual
        Pcre::match('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
    }

    public function testMatchAll(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_match_all() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::matchAll('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
    }

    public function testReplace(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::replace('/(?:\D+|<\d+>)*[!?]/', '', 'foobar foobar foobar');
    }

    public function testReplaceCallback(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace_callback() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::replaceCallback('/(?:\D+|<\d+>)*[!?]/', fn() => '', 'foobar foobar foobar');
    }

    public function testReplaceCallbackArray(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_replace_callback_array() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::replaceCallbackArray(['/(?:\D+|<\d+>)*[!?]/' => fn() => ''], 'foobar foobar foobar');
    }

    public function testSplit(): void
    {
        $this->expectException(PcreErrorException::class);
        $this->expectExceptionMessage('Call to preg_split() failed with PREG_BACKTRACK_LIMIT_ERROR');
        Pcre::split('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
    }

    /**
     * @dataProvider quoteCharacterClassProvider
     */
    public function testQuoteCharacterClass(string $expected, string $characters, ?string $delimiter = null): void
    {
        $this->assertSame($expected, Pcre::quoteCharacterClass($characters, $delimiter));
    }

    /**
     * @return array<array{string,string,2?:string|null}>
     */
    public static function quoteCharacterClassProvider(): array
    {
        return [
            [
                '\\\\',
                '\\',
            ],
            [
                '\-',
                '-',
            ],
            [
                '\^',
                '^',
            ],
            [
                '\]',
                ']',
            ],
            [
                '[',
                '[',
            ],
            [
                '|',
                '|',
            ],
            [
                '/',
                '/',
            ],
            [
                '/',
                '/',
                '',
            ],
            [
                '\/',
                '/',
                '/',
            ],
            [
                '\]\-\^\\\\@\/',
                ']-^\@/',
                '/',
            ],
            [
                '\]\-\^\\\\\\@/',
                ']-^\@/',
                '@',
            ],
            [
                'a\-z0\-9',
                'a-z0-9',
            ],
        ];
    }
}
