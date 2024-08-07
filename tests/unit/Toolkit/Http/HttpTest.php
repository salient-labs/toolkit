<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Core\MimeType;
use Salient\Http\Http;
use Salient\Tests\TestCase;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * @covers \Salient\Http\Http
 */
final class HttpTest extends TestCase
{
    /**
     * @dataProvider isRequestMethodProvider
     */
    public function testIsRequestMethod(bool $expected, string $method): void
    {
        $this->assertSame($expected, Http::isRequestMethod($method));
    }

    /**
     * @return array<array{bool,string}>
     */
    public static function isRequestMethodProvider(): array
    {
        return [
            [true, 'GET'],
            [true, 'HEAD'],
            [true, 'POST'],
            [true, 'PUT'],
            [true, 'PATCH'],
            [true, 'DELETE'],
            [true, 'CONNECT'],
            [true, 'OPTIONS'],
            [true, 'TRACE'],
            [false, ''],
            [false, 'GET '],
            [false, 'PROPFIND'],
            [false, 'get'],
        ];
    }

    /**
     * @dataProvider mediaTypeIsProvider
     */
    public function testMediaTypeIs(bool $expected, string $type, string $mimeType): void
    {
        $this->assertSame($expected, Http::mediaTypeIs($type, $mimeType));
    }

    /**
     * @return array<array{bool,string,string}>
     */
    public static function mediaTypeIsProvider(): array
    {
        return [
            [true, 'application/jwk-set+json', 'application/jwk-set'],
            [true, 'application/jwk-set+json', MimeType::JSON],
            [true, 'application/xml', MimeType::XML],
            [true, 'APPLICATION/XML', MimeType::XML],
            [false, 'application/xml-dtd', MimeType::XML],
            [true, 'application/rss+xml', MimeType::XML],
            [true, 'text/xml', MimeType::XML],
            [true, 'Text/HTML;Charset="utf-8"', MimeType::HTML],
            [false, 'Text/HTML;Charset="utf-8"', MimeType::TEXT],
        ];
    }

    public function testGetDate(): void
    {
        $date = new DateTimeImmutable('2021-10-02T17:23:14+10:00');
        $this->assertSame('Sat, 02 Oct 2021 07:23:14 GMT', Http::getDate($date));
    }

    /**
     * @dataProvider getParametersProvider
     *
     * @param string[]|string $expected
     */
    public function testGetParameters(
        $expected,
        string $value,
        bool $firstIsParameter = false,
        bool $unquote = true,
        bool $strict = false
    ): void {
        $this->maybeExpectException($expected);
        $this->assertSame($expected, Http::getParameters($value, $firstIsParameter, $unquote, $strict));
    }

    /**
     * @return array<array{string[]|string,string,2?:bool,3?:bool,4?:bool}>
     */
    public static function getParametersProvider(): array
    {
        return [
            [
                [''],
                '',
            ],
            [
                [],
                '',
                true,
            ],
            [
                InvalidArgumentException::class . ',Invalid parameter: ',
                '',
                true,
                true,
                true,
            ],
            [
                ['foo=bar', 'baz' => ''],
                'foo=bar;baz',
            ],
            [
                ['foo' => 'bar', 'baz' => ''],
                'foo=bar;baz',
                true,
            ],
            [
                ['foo' => 'bar', 'baz' => ''],
                'foo = bar; baz;',
                true,
            ],
            [
                ['foo' => 'bar', 'baz' => ''],
                'Foo=bar;;Baz',
                true,
            ],
            [
                ['Not a token', 'foo' => 'bar', 'baz' => ''],
                '"Not a token";Foo=bar;Baz=',
                false,
                true,
                true,
            ],
            [
                ['"Double \"Quotes\""', 'foo' => 'bar', 'baz' => '"Escaped\\\\Backslash"'],
                '"Double \"Quotes\"";Foo=bar;Baz="Escaped\\\\Backslash"',
                false,
                false,
                true,
            ],
            [
                InvalidArgumentException::class . ',Invalid parameter: "Not a token"',
                '"Not a token";Foo=bar;Baz=',
                true,
                true,
                true,
            ],
            [
                ['foo' => 'bar', 'baz' => '', 'qux' => 'Double "Quotes"'],
                'Foo=bar;Baz;QUX="Double \"Quotes\""',
                true,
            ],
            [
                ['foo' => 'bar', 'baz' => '', 'qux' => '"Double \"Quotes\""'],
                'Foo=bar;Baz;QUX="Double \"Quotes\""',
                true,
                false,
            ],
        ];
    }

    /**
     * @dataProvider mergeParametersProvider
     *
     * @param string[] $value
     */
    public function testMergeParameters(
        string $expected,
        array $value
    ): void {
        $this->assertSame($expected, Http::mergeParameters($value));
    }

    /**
     * @return array<array{string,string[]}>
     */
    public static function mergeParametersProvider(): array
    {
        return [
            [
                '',
                [],
            ],
            [
                '',
                [''],
            ],
            [
                ';',
                ['', ''],
            ],
            [
                '; ;',
                ['', '', ''],
            ],
            [
                '"foo=bar"; baz',
                ['foo=bar', 'baz' => ''],
            ],
            [
                'foo=bar; baz',
                ['foo' => 'bar', 'baz' => ''],
            ],
            [
                '"Not a token"; Foo=bar; Baz',
                ['Not a token', 'Foo' => 'bar', 'Baz' => ''],
            ],
            [
                'Foo=bar; Baz; QUX="Double \"Quotes\""',
                ['Foo' => 'bar', 'Baz' => '', 'QUX' => 'Double "Quotes"'],
            ],
        ];
    }

    public function testGetProduct(): void
    {
        $this->assertStringEndsWith(
            sprintf(' php/%s', \PHP_VERSION),
            Http::getProduct(),
        );
    }

    public function testMaybeQuoteString(): void
    {
        $this->assertSame('token', Http::maybeQuoteString('token'));
        $this->assertSame('another-token!', Http::maybeQuoteString('another-token!'));
        $this->assertSame('"not a token"', Http::maybeQuoteString('not a token'));
        $this->assertSame('"colon:delimited"', Http::maybeQuoteString('colon:delimited'));
        $this->assertSame('"escap\\\\ed"', Http::maybeQuoteString('escap\ed'));
        $this->assertSame('"double \"quotes\""', Http::maybeQuoteString('double "quotes"'));
    }

    public function testUnquoteString(): void
    {
        $this->assertSame('token', Http::unquoteString('token'));
        $this->assertSame('not a token', Http::unquoteString('"not a token"'));
        $this->assertSame('colon:delimited', Http::unquoteString('"colon:delimited"'));
        $this->assertSame('escap\ed', Http::unquoteString('"escap\\\\ed"'));
        $this->assertSame('double "quotes"', Http::unquoteString('"double \"quotes\""'));
    }
}
