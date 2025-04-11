<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Contract\Http\HasHttpHeader;
use Salient\Http\Message\Request;
use Salient\Http\Uri;
use Salient\Tests\TestCase;
use Salient\Utility\Str;
use InvalidArgumentException;

/**
 * Some tests are derived from similar guzzlehttp/psr7 tests
 *
 * @covers \Salient\Http\Message\Request
 * @covers \Salient\Http\Message\AbstractRequest
 * @covers \Salient\Http\Message\AbstractMessage
 * @covers \Salient\Http\HasInnerHeadersTrait
 * @covers \Salient\Http\Headers
 */
final class HttpRequestTest extends TestCase implements HasHttpHeader
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:15.0) Gecko/20100101 Firefox/15.0.1';

    /**
     * @dataProvider preserveHostProvider
     */
    public function testHostIsPreserved(
        string $expected,
        ?string $requestHostHeader,
        ?string $requestHostComponent,
        ?string $uriHostComponent
    ): void {
        if ($requestHostHeader !== null) {
            $headers = ['Host' => [$requestHostHeader]];
            $host = $requestHostHeader;
        }

        if ($requestHostComponent !== null) {
            $uri = "http://{$requestHostComponent}";
            $host ??= $requestHostComponent;
        }

        $r = new Request(
            'GET',
            $uri ?? '',
            null,
            $headers ?? null,
        );

        $this->assertSame($host ?? '', $r->getHeaderLine('host'));

        if ($uriHostComponent !== null) {
            $r = $r->withUri(new Uri("http://{$uriHostComponent}"), true);
        }

        $this->assertSame($expected, $r->getHeaderLine('host'));
    }

    /**
     * @return array<array{string,string|null,string|null,string|null}>
     */
    public static function preserveHostProvider(): array
    {
        return [
            // From PSR-7 Section 1.2
            ['', null, null, null],
            ['foo.com', null, 'foo.com', null],
            ['foo.com', null, 'foo.com', 'bar.com'],
            ['foo.com', 'foo.com', null, 'bar.com'],
            ['foo.com', 'foo.com', 'bar.com', 'baz.com'],
        ];
    }

    public function testConstructor(): void
    {
        $r = new Request('GET', '/');
        $this->assertSame('/', (string) $r->getUri());
        $this->assertInstanceOf(PsrStreamInterface::class, $r->getBody());
        $this->assertSame('', (string) $r->getBody());

        $uri = new Uri('/');
        $r = new Request('GET', $uri);
        $this->assertSame($uri, $r->getUri());

        $r = new Request('GET', '/', 'baz');
        $this->assertInstanceOf(PsrStreamInterface::class, $r->getBody());
        $this->assertSame('baz', (string) $r->getBody());

        $r = new Request('GET', '/', '0');
        $this->assertInstanceOf(PsrStreamInterface::class, $r->getBody());
        $this->assertSame('0', (string) $r->getBody());

        $r = new Request('GET', '/', Str::toStream('baz'));
        $this->assertInstanceOf(PsrStreamInterface::class, $r->getBody());
        $this->assertSame('baz', (string) $r->getBody());

        $r = new Request('GET', '');
        $this->assertSame('/', $r->getRequestTarget());

        $r = new Request('GET', '*');
        $this->assertSame('*', $r->getRequestTarget());

        $r = new Request('GET', new Uri('http://foo.com/bar baz/'));
        $this->assertSame('/bar%20baz/', $r->getRequestTarget());

        $r = new Request('GET', 'http://foo.com/baz?bar=bam');
        $this->assertSame('/baz?bar=bam', $r->getRequestTarget());

        $r = new Request('GET', 'http://foo.com/baz?bar=bam#qux');
        $this->assertSame('/baz?bar=bam', $r->getRequestTarget());

        $r = new Request('GET', 'http://foo.com/baz?0');
        $this->assertSame('/baz?0', $r->getRequestTarget());

        $h = ['User-Agent' => self::USER_AGENT];
        $r = new Request('GET', 'https://example.com/', null, $h);
        $this->assertSame($headers = [
            'Host' => ['example.com'],
            'User-Agent' => [self::USER_AGENT],
        ], $r->getHeaders());
        $this->assertSame($headers, $r->getInnerHeaders()->getHeaders());
        $this->assertSame([
            'method' => 'GET',
            'url' => 'https://example.com/',
            'httpVersion' => 'HTTP/1.1',
            'cookies' => [],
            'headers' => [
                ['name' => 'User-Agent', 'value' => self::USER_AGENT],
                ['name' => 'Host', 'value' => 'example.com'],
            ],
            'queryString' => [],
            'headersSize' => strlen(self::USER_AGENT) + 51,
            'bodySize' => 0,
        ], $r->jsonSerialize());

        $h = ['Foo' => ['a', 'b', 'c']];
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', null, $h);
        $this->assertSame('a, b, c', $r->getHeaderLine('Foo'));
        $this->assertSame('', $r->getHeaderLine('Bar'));

        $h = [
            'ZOO' => 'zoobar',
            'zoo' => ['foobar', 'zoobar'],
        ];
        $r = new Request('GET', '', null, $h);
        $this->assertSame(['ZOO' => ['zoobar', 'foobar', 'zoobar']], $r->getHeaders());
        $this->assertSame('zoobar, foobar, zoobar', $r->getHeaderLine('zoo'));

        $r = new Request('GET', 'http://foo.com:8124/bar');
        $this->assertSame('foo.com:8124', $r->getHeaderLine('host'));

        $r = new Request('GET', 'http://foo.com:8124/bar');
        $r = $r->withUri(new Uri('http://foo.com:8125/bar'));
        $this->assertSame('foo.com:8125', $r->getHeaderLine('host'));
    }

    public function testConstructorWithInvalidProtocolVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP protocol version: 2.');
        new Request('GET', '/', null, null, null, '2.');
    }

    public function testWithInvalidProtocolVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP protocol version: 2.');
        (new Request('GET', '/'))->withProtocolVersion('2.');
    }

    public function testConstructorWithInvalidBody(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($body) must be of type ' . PsrStreamInterface::class . '|resource|string|null, int given');
        // @phpstan-ignore argument.type
        new Request('GET', '/', 123);
    }

    public function testWithInvalidBody(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($body) must be of type ' . PsrStreamInterface::class . '|resource|string|null, int given');
        // @phpstan-ignore argument.type
        (new Request('GET', '/'))->withBody(123);
    }

    public function testConstructorWithInvalidUri(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Request('GET', '//example.com:-1');
    }

    public function testWithMethod(): void
    {
        $r1 = new Request('GET', '/');
        $r2 = $r1->withMethod('GET');
        $r3 = $r2->withMethod('POST');
        $this->assertSame($r1, $r2);
        $this->assertNotSame($r2, $r3);
        $this->assertSame('GET', $r1->getMethod());
        $this->assertSame('POST', $r3->getMethod());
    }

    public function testMethodCaseSensitivity(): void
    {
        $r = new Request('post', '/');
        $this->assertSame('post', $r->getMethod());

        $r = new Request('GET', '/');
        $this->assertSame('put', $r->withMethod('put')->getMethod());
    }

    public function testWithUri(): void
    {
        $r1 = new Request('GET', '/');
        $u1 = $r1->getUri();
        $u2 = new Uri('http://example.com');
        $r2 = $r1->withUri($u2);
        $this->assertNotSame($r1, $r2);
        $this->assertSame($u1, $r1->getUri());
        $this->assertSame($u2, $r2->getUri());
    }

    /**
     * @dataProvider invalidMethodsProvider
     */
    public function testConstructorWithInvalidMethods(string $method): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP method: ' . $method);
        new Request($method, '/');
    }

    /**
     * @dataProvider invalidMethodsProvider
     */
    public function testWithInvalidMethods(string $method): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid HTTP method: ' . $method);
        (new Request('GET', '/'))->withMethod($method);
    }

    /**
     * @return array<array{string}>
     */
    public function invalidMethodsProvider(): array
    {
        return [
            [''],
            ['"GET"'],
        ];
    }

    public function testWithRequestTarget(): void
    {
        $r1 = new Request('GET', '/');
        $r2 = $r1->withRequestTarget('*');
        $this->assertSame('/', $r1->getRequestTarget());
        $this->assertSame('*', $r2->getRequestTarget());
    }

    public function testWithInvalidRequestTarget(): void
    {
        $r = new Request('GET', '/');
        $this->expectException(InvalidArgumentException::class);
        $r->withRequestTarget('//example.com:-1');
    }

    public function testImmutability(): void
    {
        $r1 = new Request('GET', 'http://example.com');
        $r2 = $r1->withUri($r1->getUri());
        $r3 = $r2->withUri(new Uri('http://example.com'));
        $r4 = $r3->withUri(new Uri('https://example.com'));
        $r5 = $r4->withHeader(self::HEADER_HOST, 'example.com');
        $r6 = $r5->withHeader(self::HEADER_HOST, 'foo.com');
        $r7 = $r6->withRequestTarget('/');
        $r8 = $r7->withRequestTarget('/');
        $this->assertSame($r1, $r2);
        $this->assertSame($r2, $r3);
        $this->assertNotSame($r3, $r4);
        $this->assertSame($r4, $r5);
        $this->assertNotSame($r5, $r6);
        $this->assertNotSame($r6, $r7);
        $this->assertSame($r7, $r8);
    }

    /**
     * @dataProvider invalidHeaderNamesProvider
     */
    public function testInvalidHeaderNames(string $header): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid header name: %s', $header));
        $h = [$header => 'value'];
        new Request('GET', 'http://foo.com/baz?bar=bam', null, $h);
    }

    /**
     * @return array<array{string}>
     */
    public function invalidHeaderNamesProvider(): array
    {
        return [
            [' key '],
            ['key '],
            [' key'],
            ['key/'],
            ['key('],
            ['key\\'],
            [' '],
        ];
    }

    /**
     * @dataProvider validHeaderNamesProvider
     */
    public function testValidHeaderNames(string $header): void
    {
        $h = [$header => 'value'];
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', null, $h);
        $this->assertArrayHasKey($header, $r->getHeaders());
    }

    /**
     * @return array<array{string}>
     */
    public function validHeaderNamesProvider(): array
    {
        return [
            ['key'],
            ['key#'],
            ['key$'],
            ['key%'],
            ['key&'],
            ['key*'],
            ['key+'],
            ['key.'],
            ['key^'],
            ['key_'],
            ['key|'],
            ['key~'],
            ['key!'],
            ['key-'],
            ["key'"],
            ['key`'],
        ];
    }

    public function testWithUriUpdatesHost(): void
    {
        $r1 = new Request('GET', 'http://foo.com/baz?bar=bam');
        $this->assertSame(['Host' => ['foo.com']], $r1->getHeaders());
        $r2 = $r1->withUri(new Uri('http://baz.com/bar'));
        $this->assertSame('baz.com', $r2->getHeaderLine('Host'));
    }

    /**
     * @dataProvider invalidHeaderValuesProvider
     */
    public function testInvalidHeaderValues(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Invalid header value: %s', $value));
        $h = ['foo' => $value];
        new Request('GET', 'http://foo.com/baz?bar=bam', null, $h);
    }

    /**
     * @return array<array{string}>
     */
    public function invalidHeaderValuesProvider(): array
    {
        $values = [
            ["new\nline"],
            ["new\r\nline"],
            ["new\rline"],
            ["newline\n"],
            ["\nnewline"],
            ["newline\r\n"],
            ["\r\nnewline"],
        ];

        for ($i = 0; $i < 0x80; $i++) {
            if ($i === 0x9 || ($i >= 0x20 && $i <= 0x7E)) {
                continue;
            }
            $values[] = ['foo' . chr($i) . 'bar'];
            $values[] = ['foo' . chr($i)];
        }

        return $values;
    }
}
