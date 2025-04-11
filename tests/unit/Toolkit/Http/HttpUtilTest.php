<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Http\HasMediaType;
use Salient\Http\HttpUtil;
use Salient\Http\Request;
use Salient\Tests\TestCase;
use DateTimeImmutable;
use InvalidArgumentException;
use Stringable;

/**
 * @covers \Salient\Http\HttpUtil
 */
final class HttpUtilTest extends TestCase implements HasMediaType
{
    /**
     * @dataProvider isRequestMethodProvider
     */
    public function testIsRequestMethod(bool $expected, string $method): void
    {
        $this->assertSame($expected, HttpUtil::isRequestMethod($method));
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

    public function testRequestTargetIsAuthorityForm(): void
    {
        $this->assertTrue(HttpUtil::requestTargetIsAuthorityForm('example.com:80'));
        $this->assertFalse(HttpUtil::requestTargetIsAuthorityForm('example.com'));
        $this->assertFalse(HttpUtil::requestTargetIsAuthorityForm('example.com:80/path'));
        $this->assertFalse(HttpUtil::requestTargetIsAuthorityForm('example.com:80?query'));
        $this->assertFalse(HttpUtil::requestTargetIsAuthorityForm('http://example.com:80'));
        $this->assertFalse(HttpUtil::requestTargetIsAuthorityForm('http://example.com:80/path'));
        $this->assertFalse(HttpUtil::requestTargetIsAuthorityForm('http://example.com:80?query'));
    }

    /**
     * @dataProvider mediaTypeIsProvider
     */
    public function testMediaTypeIs(bool $expected, string $type, string $mimeType): void
    {
        $this->assertSame($expected, HttpUtil::mediaTypeIs($type, $mimeType));
    }

    /**
     * @return array<array{bool,string,string}>
     */
    public static function mediaTypeIsProvider(): array
    {
        return [
            [true, 'application/jwk-set+json', 'application/jwk-set'],
            [true, 'application/jwk-set+json', self::TYPE_JSON],
            [true, 'application/xml', self::TYPE_XML],
            [true, 'APPLICATION/XML', self::TYPE_XML],
            [false, 'application/xml-dtd', self::TYPE_XML],
            [true, 'application/rss+xml', self::TYPE_XML],
            [true, 'text/xml', self::TYPE_XML],
            [true, 'Text/HTML;Charset="utf-8"', self::TYPE_HTML],
            [false, 'Text/HTML;Charset="utf-8"', self::TYPE_TEXT],
        ];
    }

    public function testGetDate(): void
    {
        $date = new DateTimeImmutable('2021-10-02T17:23:14+10:00');
        $this->assertSame('Sat, 02 Oct 2021 07:23:14 GMT', HttpUtil::getDate($date));
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
        $this->assertSame($expected, HttpUtil::getParameters($value, $firstIsParameter, $unquote, $strict));
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
        $this->assertSame($expected, HttpUtil::mergeParameters($value));
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
            HttpUtil::getProduct(),
        );
    }

    public function testMaybeQuoteString(): void
    {
        $this->assertSame('token', HttpUtil::maybeQuoteString('token'));
        $this->assertSame('another-token!', HttpUtil::maybeQuoteString('another-token!'));
        $this->assertSame('"not a token"', HttpUtil::maybeQuoteString('not a token'));
        $this->assertSame('"colon:delimited"', HttpUtil::maybeQuoteString('colon:delimited'));
        $this->assertSame('"escap\\\\ed"', HttpUtil::maybeQuoteString('escap\ed'));
        $this->assertSame('"double \"quotes\""', HttpUtil::maybeQuoteString('double "quotes"'));
    }

    public function testUnquoteString(): void
    {
        $this->assertSame('token', HttpUtil::unquoteString('token'));
        $this->assertSame('not a token', HttpUtil::unquoteString('"not a token"'));
        $this->assertSame('colon:delimited', HttpUtil::unquoteString('"colon:delimited"'));
        $this->assertSame('escap\ed', HttpUtil::unquoteString('"escap\\\\ed"'));
        $this->assertSame('double "quotes"', HttpUtil::unquoteString('"double \"quotes\""'));
    }

    /**
     * @dataProvider mergeQueryProvider
     *
     * @param PsrUriInterface|Stringable|string $uri
     * @param mixed[] $data
     * @param int-mask-of<HttpUtil::DATA_*> $flags
     */
    public function testMergeQuery(
        string $expected,
        $uri,
        array $data,
        int $flags = HttpUtil::DATA_PRESERVE_NUMERIC_KEYS | HttpUtil::DATA_PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null
    ): void {
        $this->assertSame(
            $expected,
            (string) HttpUtil::mergeQuery($uri, $data, $flags, $dateFormatter),
        );
        $request = new Request('GET', $uri);
        $request = HttpUtil::mergeQuery($request, $data, $flags, $dateFormatter);
        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame($expected, (string) $request->getUri());
    }

    /**
     * @return array<array{string,PsrUriInterface|Stringable|string,mixed[],3?:int,4?:DateFormatterInterface|null}>
     */
    public static function mergeQueryProvider(): array
    {
        return [
            [
                'http://example.com/?foo=bar&baz=qux',
                'http://example.com/?foo=bar',
                ['baz' => 'qux'],
            ],
            [
                'http://example.com/?foo=BAR&baz=qux',
                'http://example.com/?foo=bar',
                ['foo' => 'BAR', 'baz' => 'qux'],
            ],
            [
                // http://example.com/?foo[]=pi&foo[]=3.14&foo[]=1
                'http://example.com/?foo%5B%5D=pi&foo%5B%5D=3.14&foo%5B%5D=1',
                'http://example.com/?foo=bar',
                ['foo' => ['pi', 3.14, true]],
            ],
            [
                // http://example.com/?foo[]=-1&foo[]=0&foo[]=qux
                'http://example.com/?foo%5B%5D=-1&foo%5B%5D=0&foo%5B%5D=qux',
                // http://example.com/?foo[]=bar&foo[]=baz&foo[]=qux
                'http://example.com/?foo%5B%5D=bar&foo%5B%5D=baz&foo%5B%5D=qux',
                ['foo' => [-1, 0]],
            ],
            [
                'http://example.com/?foo=BAR',
                // http://example.com/?foo[]=bar&foo[]=baz&foo[]=qux
                'http://example.com/?foo%5B%5D=bar&foo%5B%5D=baz&foo%5B%5D=qux',
                ['foo' => 'BAR'],
            ],
        ];
    }

    /**
     * @dataProvider replaceQueryProvider
     *
     * @param PsrUriInterface|Stringable|string $uri
     * @param mixed[] $data
     * @param int-mask-of<HttpUtil::DATA_*> $flags
     */
    public function testReplaceQuery(
        string $expected,
        $uri,
        array $data,
        int $flags = HttpUtil::DATA_PRESERVE_NUMERIC_KEYS | HttpUtil::DATA_PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null
    ): void {
        $this->assertSame(
            $expected,
            (string) HttpUtil::replaceQuery($uri, $data, $flags, $dateFormatter),
        );
        $request = new Request('GET', $uri);
        $request = HttpUtil::replaceQuery($request, $data, $flags, $dateFormatter);
        $this->assertInstanceOf(Request::class, $request);
        $this->assertSame($expected, (string) $request->getUri());
    }

    /**
     * @return array<array{string,PsrUriInterface|Stringable|string,mixed[],3?:int,4?:DateFormatterInterface|null}>
     */
    public static function replaceQueryProvider(): array
    {
        return [
            [
                'http://example.com/?baz=qux',
                'http://example.com/?foo=bar',
                ['baz' => 'qux'],
            ],
            [
                'http://example.com/?foo=BAR&baz=qux',
                'http://example.com/?foo=bar',
                ['foo' => 'BAR', 'baz' => 'qux'],
            ],
            [
                // http://example.com/?foo[]=pi&foo[]=3.14&foo[]=1
                'http://example.com/?foo%5B%5D=pi&foo%5B%5D=3.14&foo%5B%5D=1',
                'http://example.com/?baz=qux',
                ['foo' => ['pi', 3.14, true]],
            ],
            [
                // http://example.com/?foo[]=-1&foo[]=0
                'http://example.com/?foo%5B%5D=-1&foo%5B%5D=0',
                // http://example.com/?foo[]=bar&foo[]=baz&foo[]=qux
                'http://example.com/?foo%5B%5D=bar&foo%5B%5D=baz&foo%5B%5D=qux',
                ['foo' => [-1, 0]],
            ],
            [
                'http://example.com/?foo=BAR',
                // http://example.com/?foo[]=bar&foo[]=baz&foo[]=qux
                'http://example.com/?foo%5B%5D=bar&foo%5B%5D=baz&foo%5B%5D=qux',
                ['foo' => 'BAR'],
            ],
        ];
    }
}
