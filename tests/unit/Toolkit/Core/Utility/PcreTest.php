<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Lkrms\Tests\TestCase;
use Salient\Core\Exception\PcreErrorException;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Pcre;
use Stringable;

/**
 * @covers \Salient\Core\Utility\Pcre
 * @covers \Salient\Core\Exception\PcreErrorException
 */
final class PcreTest extends TestCase
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
     * @template TValue of int|float|string|bool|Stringable|null
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
        $obj = new class implements Stringable {
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

    public function testMatchException(): void
    {
        try {
            Pcre::match('/(?:\D+|<\d+>)*[!?]/', 'foobar foobar foobar');
        } catch (PcreErrorException $e) {
            $this->assertStringContainsString('Call to preg_match() failed with PREG_BACKTRACK_LIMIT_ERROR', $e->getMessage());
            $this->assertSame([
                'PcreError' => (string) \PREG_BACKTRACK_LIMIT_ERROR,
                'Pattern' => '/(?:\D+|<\d+>)*[!?]/',
                'Subject' => 'foobar foobar foobar',
            ], $e->getDetail());
            $this->assertSame($e->getPcreError(), \PREG_BACKTRACK_LIMIT_ERROR);
            $this->assertSame($e->getPcreErrorName(), 'PREG_BACKTRACK_LIMIT_ERROR');
            $this->assertSame($e->getPcreErrorMessage(), 'Backtrack limit exhausted');
            $this->assertSame($e->getFunction(), 'preg_match');
            $this->assertSame($e->getPattern(), '/(?:\D+|<\d+>)*[!?]/');
            $this->assertSame($e->getSubject(), 'foobar foobar foobar');
        }
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

    public function testReplaceException(): void
    {
        $pattern = ['/(?:\D+|<\d+>)*[!?]/', '/\h+/'];
        try {
            Pcre::replace($pattern, ['', ' '], 'foobar foobar foobar');
        } catch (PcreErrorException $e) {
            $this->assertStringContainsString(
                'Call to preg_replace() failed with PREG_BACKTRACK_LIMIT_ERROR',
                $e->getMessage(),
            );
            $this->assertSame([
                'PcreError' => (string) \PREG_BACKTRACK_LIMIT_ERROR,
                'Pattern' => Json::prettyPrint($pattern),
                'Subject' => 'foobar foobar foobar',
            ], $e->getDetail());
            $this->assertSame($e->getPcreError(), \PREG_BACKTRACK_LIMIT_ERROR);
            $this->assertSame($e->getPcreErrorName(), 'PREG_BACKTRACK_LIMIT_ERROR');
            $this->assertSame($e->getPcreErrorMessage(), 'Backtrack limit exhausted');
            $this->assertSame($e->getFunction(), 'preg_replace');
            $this->assertSame($e->getPattern(), $pattern);
            $this->assertSame($e->getSubject(), 'foobar foobar foobar');
        }
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
