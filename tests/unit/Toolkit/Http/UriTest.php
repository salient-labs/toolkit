<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Pcre;
use Salient\Http\Uri;
use Salient\Tests\TestCase;
use Generator;

/**
 * Some tests are derived from similar guzzlehttp/psr7 tests
 *
 * @covers \Salient\Http\Uri
 */
final class UriTest extends TestCase
{
    private const COMPONENT_MAP = [
        'scheme' => \PHP_URL_SCHEME,
        'host' => \PHP_URL_HOST,
        'port' => \PHP_URL_PORT,
        'user' => \PHP_URL_USER,
        'pass' => \PHP_URL_PASS,
        'path' => \PHP_URL_PATH,
        'query' => \PHP_URL_QUERY,
        'fragment' => \PHP_URL_FRAGMENT,
    ];

    /**
     * @dataProvider constructorProvider
     */
    public function testConstructor(
        string $uri,
        string $scheme = '',
        string $authority = '',
        string $userInfo = '',
        string $host = '',
        ?int $port = null,
        string $path = '',
        string $query = '',
        string $fragment = ''
    ): void {
        $expected = $uri;
        $uri = new Uri($uri);

        $this->assertSame($scheme, $uri->getScheme());
        $this->assertSame($authority, $uri->getAuthority());
        $this->assertSame($userInfo, $uri->getUserInfo());
        $this->assertSame($host, $uri->getHost());
        $this->assertSame($port, $uri->getPort());
        $this->assertSame($path, $uri->getPath());
        $this->assertSame($query, $uri->getQuery());
        $this->assertSame($fragment, $uri->getFragment());
        $this->assertSame($expected, (string) $uri);
        $this->assertSame(json_encode($expected), json_encode($uri));
    }

    /**
     * @return array<array<string|int|null>>
     */
    public static function constructorProvider(): array
    {
        return [
            [
                'https://user:pass@example.com:8080/path/123?q=abc#test',
                'https',
                'user:pass@example.com:8080',
                'user:pass',
                'example.com',
                8080,
                '/path/123',
                'q=abc',
                'test',
            ],
            [
                'urn:path-rootless',
                'urn',
                '',
                '',
                '',
                null,
                'path-rootless',
            ],
            [
                'urn:path:with:colon',
                'urn',
                '',
                '',
                '',
                null,
                'path:with:colon',
            ],
            [
                'urn:/path-absolute',
                'urn',
                '',
                '',
                '',
                null,
                '/path-absolute',
            ],
            [
                'urn:/',
                'urn',
                '',
                '',
                '',
                null,
                '/',
            ],
            [
                'urn:',
                'urn',
            ],
            [
                '/',
                '',
                '',
                '',
                '',
                null,
                '/',
            ],
            [
                'relative/',
                '',
                '',
                '',
                '',
                null,
                'relative/',
            ],
            [
                '0',
                '',
                '',
                '',
                '',
                null,
                '0',
            ],
            [
                '',
            ],
            [
                '//example.org',
                '',
                'example.org',
                '',
                'example.org',
            ],
            [
                '//example.org/',
                '',
                'example.org',
                '',
                'example.org',
                null,
                '/',
            ],
            [
                '//example.org?q#h',
                '',
                'example.org',
                '',
                'example.org',
                null,
                '',
                'q',
                'h',
            ],
            [
                '?q',
                '',
                '',
                '',
                '',
                null,
                '',
                'q',
            ],
            [
                '?q=abc&foo=bar',
                '',
                '',
                '',
                '',
                null,
                '',
                'q=abc&foo=bar',
            ],
            [
                '#fragment',
                '',
                '',
                '',
                '',
                null,
                '',
                '',
                'fragment',
            ],
            [
                './foo/../bar',
                '',
                '',
                '',
                '',
                null,
                './foo/../bar',
            ],
            [
                'file:///etc/hosts',
                'file',
                '',
                '',
                '',
                null,
                '/etc/hosts',
            ],
            [
                'http://',
                'http',
            ]
        ];
    }

    /**
     * @dataProvider invalidUriProvider
     */
    public function testInvalidUri(string $uri): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid URI: $uri");
        new Uri($uri);
    }

    /**
     * @return array<array{string}>
     */
    public static function invalidUriProvider(): array
    {
        return [
            ['//example.com:-1'],
            ['http://host:8080;params'],
            ['urn://host:with:colon'],
            [':path:with:colon'],
        ];
    }

    /**
     * @dataProvider isReferenceProvider
     */
    public function testIsReference(string $uri): void
    {
        $this->assertTrue((new Uri($uri))->isReference());
    }

    /**
     * @return array<array{string}>
     */
    public static function isReferenceProvider(): array
    {
        return [
            ['//'],
            ['///'],
        ];
    }

    public function testWith(): void
    {
        $uri = (new Uri())
            ->withScheme('https')
            ->withHost('example.com')
            ->withUserInfo('user', 'pass')
            ->withPort(8080)
            ->withPath('/path/123')
            ->withQuery('q=abc')
            ->withFragment('test');

        $this->assertSame('https', $uri->getScheme());
        $this->assertSame('user:pass@example.com:8080', $uri->getAuthority());
        $this->assertSame('user:pass', $uri->getUserInfo());
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame(8080, $uri->getPort());
        $this->assertSame('/path/123', $uri->getPath());
        $this->assertSame('q=abc', $uri->getQuery());
        $this->assertSame('test', $uri->getFragment());
        $this->assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    public function testWithInvalidScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid scheme: invalid_scheme');
        (new Uri())->withScheme('invalid_scheme');
    }

    public function testUriWithInvalidScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URI: invalid_scheme://example.com');
        new Uri('invalid_scheme://example.com');
    }

    public function testWithInvalidHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid host: [::1].localhost');
        (new Uri())->withHost('[::1].localhost');
    }

    public function testUriWithInvalidHost(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URI: http://[::1].localhost');
        new Uri('http://[::1].localhost');
    }

    public function testWithInvalidPort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: 100000');
        (new Uri())->withHost('example.com')->withPort(100000);
    }

    public function testWithNegativePort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: -1');
        (new Uri())->withHost('example.com')->withPort(-1);
    }

    public function testUriWithInvalidPort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid URI: //example.com:-1');
        new Uri('//example.com:-1');
    }

    public function testFalseyUri(): void
    {
        $uri = new Uri('//0:0@0/0?0#0');

        $this->assertSame('', $uri->getScheme());
        $this->assertSame('0:0@0', $uri->getAuthority());
        $this->assertSame('0:0', $uri->getUserInfo());
        $this->assertSame('0', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('/0', $uri->getPath());
        $this->assertSame('0', $uri->getQuery());
        $this->assertSame('0', $uri->getFragment());
        $this->assertSame('//0:0@0/0?0#0', (string) $uri);
    }

    public function testWithFalsey(): void
    {
        $uri = (new Uri())
            ->withHost('0')
            ->withUserInfo('0', '0')
            ->withPath('/0')
            ->withQuery('0')
            ->withFragment('0');

        $this->assertSame('', $uri->getScheme());
        $this->assertSame('0:0@0', $uri->getAuthority());
        $this->assertSame('0:0', $uri->getUserInfo());
        $this->assertSame('0', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('/0', $uri->getPath());
        $this->assertSame('0', $uri->getQuery());
        $this->assertSame('0', $uri->getFragment());
        $this->assertSame('//0:0@0/0?0#0', (string) $uri);
    }

    /**
     * @dataProvider encodingProvider
     *
     * @param array<string,string|int|null> $expectedParts
     */
    public function testEncoding(
        string $expected,
        string $uri,
        array $expectedParts,
        bool $strict = false
    ): void {
        $uri = new Uri($uri, $strict);
        $this->assertSame($expected, (string) $uri);
        foreach ($expectedParts as $part => $expected) {
            $this->assertSame($expected, $uri->{"get$part"}());
        }
    }

    /**
     * @return array<string,array{string,string,array<string,string|int|null>,3?:bool}>
     */
    public static function encodingProvider(): array
    {
        $encodedUnreserved = implode(array_map(
            fn(int $codepoint): string => sprintf('%%%02x', $codepoint),
            [
                ord('-'),
                ord('.'),
                ord('_'),
                ord('~'),
                ...range(ord('0'), ord('9')),
                ...range(ord('A'), ord('Z')),
                ...range(ord('a'), ord('z')),
            ]
        ));
        $unreserved = '-._~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $unreservedAndDelims = $unreserved . '!$&\'()*+,:;=@';

        return [
            'encoded unreserved -> decoded unreserved #1' => [
                "/$unreserved",
                "/$encodedUnreserved",
                [],
                true,
            ],
            'encoded unreserved -> decoded unreserved #2' => [
                '/path?q=value#fragment',
                '/p%61th?q=v%61lue#fr%61gment',
                [
                    'path' => '/path',
                    'query' => 'q=value',
                    'fragment' => 'fragment',
                ],
                true,
            ],
            'schema + host -> lowercase' => [
                'http://example.com/Path/To/Resource',
                'HTTP://EXAMPLE.COM/Path/To/Resource',
                [],
                true
            ],
            'schema + encoded host -> lowercase' => [
                'http://example.com/Path/To/Resource',
                'HTTP://%45%58%41%4d%50%4c%45.COM/Path/To/Resource',
                [],
                true,
            ],
            'encoded slash' => [
                '/segment%2Fwith%2Fembedded/slash',
                '/segment%2fwith%2fembedded/slash',
                [],
                true,
            ],
            'encoded space' => [
                '/pa%20th?q=va%20lue#frag%20ment',
                '/pa%20th?q=va%20lue#frag%20ment',
                [
                    'path' => '/pa%20th',
                    'query' => 'q=va%20lue',
                    'fragment' => 'frag%20ment',
                ],
                true,
            ],
            'unencoded space' => [
                '/pa%20th?q=va%20lue#frag%20ment',
                '/pa th?q=va lue#frag ment',
                [
                    'path' => '/pa%20th',
                    'query' => 'q=va%20lue',
                    'fragment' => 'frag%20ment',
                ],
            ],
            'unencoded multibyte' => [
                '/%E2%82%AC?%E2%82%AC#%E2%82%AC',
                '/€?€#€',
                [
                    'path' => '/%E2%82%AC',
                    'query' => '%E2%82%AC',
                    'fragment' => '%E2%82%AC',
                ],
            ],
            'invalid encoding' => [
                '/pa%252-th?q=va%252-lue#frag%252-ment',
                '/pa%2-th?q=va%2-lue#frag%2-ment',
                [
                    'path' => '/pa%252-th',
                    'query' => 'q=va%252-lue',
                    'fragment' => 'frag%252-ment',
                ],
            ],
            'path segments' => [
                '/pa/th//two?q=va/lue#frag/ment',
                '/pa/th//two?q=va/lue#frag/ment',
                [
                    'path' => '/pa/th//two',
                    'query' => 'q=va/lue',
                    'fragment' => 'frag/ment',
                ],
                true,
            ],
            'unreserved + delimiters' => [
                "/$unreservedAndDelims?$unreservedAndDelims#$unreservedAndDelims",
                "/$unreservedAndDelims?$unreservedAndDelims#$unreservedAndDelims",
                [
                    'path' => "/$unreservedAndDelims",
                    'query' => $unreservedAndDelims,
                    'fragment' => $unreservedAndDelims,
                ],
                true,
            ],
        ];
    }

    public function testNormalisation(): void
    {
        $uri = (new Uri('//example.com'))->withScheme('HTTP');
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('http://example.com', (string) $uri);

        $uri = (new Uri())->withHost('eXaMpLe.CoM');
        $this->assertSame('example.com', $uri->getHost());
        $this->assertSame('//example.com', (string) $uri);

        $uri = new Uri('https://example.com:443');
        $this->assertNull($uri->getPort());
        $this->assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('https://example.com'))->withPort(443);
        $this->assertNull($uri->getPort());
        $this->assertSame('example.com', $uri->getAuthority());

        $uri = new Uri('http://example.com:80');
        $this->assertNull($uri->getPort());
        $this->assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('http://example.com'))->withPort(80);
        $this->assertNull($uri->getPort());
        $this->assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('//example.com'))->withPort(80);
        $this->assertSame(80, $uri->getPort());
        $this->assertSame('example.com:80', $uri->getAuthority());

        $uri = new Uri('http://example.com:443');
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame(443, $uri->getPort());

        $uri = $uri->withScheme('https');
        $this->assertNull($uri->getPort());

        $uri = (new Uri('http://example.com:8080'))->withPort(null);
        $this->assertNull($uri->getPort());
        $this->assertSame('http://example.com', (string) $uri);
    }

    public function testWithEncoding(): void
    {
        $uri = (new Uri())->withPath('/baz?#€/b%61r');
        $this->assertSame('/baz%3F%23%E2%82%AC/bar', $uri->getPath());
        $this->assertSame('/baz%3F%23%E2%82%AC/bar', (string) $uri);

        $uri = (new Uri())->withQuery('?=#&€=/&b%61r');
        $this->assertSame('?=%23&%E2%82%AC=/&bar', $uri->getQuery());
        $this->assertSame('??=%23&%E2%82%AC=/&bar', (string) $uri);

        $uri = (new Uri())->withFragment('#€?/b%61r');
        $this->assertSame('%23%E2%82%AC?/bar', $uri->getFragment());
        $this->assertSame('#%23%E2%82%AC?/bar', (string) $uri);

        $uri = (new Uri())
            ->withHost('example.com')
            ->withUserInfo('foo@bar.com', 'pass#word');
        $this->assertSame('foo%40bar.com:pass%23word', $uri->getUserInfo());
        $this->assertSame('//foo%40bar.com:pass%23word@example.com', (string) $uri);

        $uri = (new Uri())
            ->withHost('example.com')
            ->withUserInfo('foo%40bar.com', 'pass%23word');
        $this->assertSame('foo%40bar.com:pass%23word', $uri->getUserInfo());
        $this->assertSame('//foo%40bar.com:pass%23word@example.com', (string) $uri);

        $uri = (new Uri())
            ->withHost('example.com')
            ->withUserInfo('foo@:bar:', 'pass:#word');
        $this->assertSame('foo%40%3Abar%3A:pass%3A%23word', $uri->getUserInfo());
        $this->assertSame('//foo%40%3Abar%3A:pass%3A%23word@example.com', (string) $uri);
    }

    public function testRelativePathWithAuthority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must be empty or begin with "/" in URI with authority');
        (new Uri())->withHost('example.com')->withPath('foo');
    }

    public function testPathWithTwoSlashesWithoutAuthority(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot begin with "//" in URI without authority');
        (new Uri())->withPath('//foo');
    }

    public function testPathWithTwoSlashes(): void
    {
        $uri = new Uri('http://example.org//path-not-host.com');
        $this->assertSame('//path-not-host.com', $uri->getPath());

        $uri = $uri->withScheme('');
        $this->assertSame('//example.org//path-not-host.com', (string) $uri);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot begin with "//" in URI without authority');
        $uri->withHost('');
    }

    public function testColonSegmentWithoutScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot begin with colon segment in URI without scheme');
        (new Uri())->withPath('mailto:foo');
    }

    public function testColonSegment(): void
    {
        $uri = (new Uri('urn:/mailto:foo'))->withScheme('');
        $this->assertSame('/mailto:foo', $uri->getPath());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot begin with colon segment in URI without scheme');
        (new Uri('urn:mailto:foo'))->withScheme('');
    }

    public function testInitialValues(): void
    {
        $uri = new Uri();

        $this->assertSame('', $uri->getScheme());
        $this->assertSame('', $uri->getAuthority());
        $this->assertSame('', $uri->getUserInfo());
        $this->assertSame('', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('', $uri->getPath());
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
    }

    public function testImmutability(): void
    {
        $uri = new Uri();

        $this->assertNotSame($uri, $uri->withScheme('https'));
        $this->assertNotSame($uri, $uri2 = $uri->withHost('example.com'));
        $this->assertNotSame($uri2, $uri2->withUserInfo('user', 'pass'));
        $this->assertNotSame($uri2, $uri2->withPort(8080));
        $this->assertNotSame($uri, $uri->withPath('/path/123'));
        $this->assertNotSame($uri, $uri->withQuery('q=abc'));
        $this->assertNotSame($uri, $uri->withFragment('test'));
    }

    public function testIPv6Host(): void
    {
        $uri = new Uri('https://[2a00:f48:1008::212:183:10]');
        $this->assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());

        $uri = new Uri('http://[2a00:f48:1008::212:183:10]:56?foo=bar');
        $this->assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());
        $this->assertSame(56, $uri->getPort());
        $this->assertSame('foo=bar', $uri->getQuery());
    }

    /**
     * @dataProvider followProvider
     */
    public function testFollow(string $expected, string $uri, string $reference): void
    {
        $uri = new Uri($uri);
        $reference = new Uri($reference);
        $target = $uri->follow($reference);
        $this->assertSame($expected, (string) $target);

        // Check the target path matches the output of the remove_dot_segments
        // algorithm given in [RFC3986]
        if (
            !$reference->isReference() ||
            $reference->getAuthority() !== '' ||
            ($reference->getPath()[0] ?? null) === '/'
        ) {
            $path = $reference->getPath();
        } elseif ($reference->getPath() === '') {
            $path = $uri->getPath();
        } elseif ($uri->getAuthority() !== '' && $uri->getPath() === '') {
            $path = '/' . $reference->getPath();
        } else {
            $path = Arr::implode('/', [
                // Remove characters after the last "/" in the base URI's path,
                // or the entire path if it doesn't have any "/" characters
                implode('/', Arr::pop(explode('/', $uri->getPath()))),
                $reference->getPath(),
            ]);
        }
        $this->assertSame($this->removeDotSegments($path), $target->getPath());
    }

    /**
     * @dataProvider followProvider
     */
    public function testResolveReference(string $expected, string $uri, string $reference): void
    {
        $this->assertSame($expected, Uri::resolveReference($reference, $uri));
    }

    /**
     * @return Generator<array{string,string,string}>
     */
    public static function followProvider(): Generator
    {
        $references = [
            // [RFC3986] Section 5.4.1 ("Normal Examples")
            ['g:h', 'g:h'],
            ['http://a/b/c/g', 'g'],
            ['http://a/b/c/g', './g'],
            ['http://a/b/c/g/', 'g/'],
            ['http://a/g', '/g'],
            ['http://g', '//g'],
            ['http://a/b/c/d;p?y', '?y'],
            ['http://a/b/c/g?y', 'g?y'],
            ['http://a/b/c/d;p?q#s', '#s'],
            ['http://a/b/c/g#s', 'g#s'],
            ['http://a/b/c/g?y#s', 'g?y#s'],
            ['http://a/b/c/;x', ';x'],
            ['http://a/b/c/g;x', 'g;x'],
            ['http://a/b/c/g;x?y#s', 'g;x?y#s'],
            ['http://a/b/c/d;p?q', ''],
            ['http://a/b/c/', '.'],
            ['http://a/b/c/', './'],
            ['http://a/b/', '..'],
            ['http://a/b/', '../'],
            ['http://a/b/g', '../g'],
            ['http://a/', '../..'],
            ['http://a/', '../../'],
            ['http://a/g', '../../g'],
            // [RFC3986] Section 5.4.1 ("Abnormal Examples")
            ['http://a/g', '../../../g'],
            ['http://a/g', '../../../../g'],
            ['http://a/g', '/./g'],
            ['http://a/g', '/../g'],
            ['http://a/b/c/g.', 'g.'],
            ['http://a/b/c/.g', '.g'],
            ['http://a/b/c/g..', 'g..'],
            ['http://a/b/c/..g', '..g'],
            ['http://a/b/g', './../g'],
            ['http://a/b/c/g/', './g/.'],
            ['http://a/b/c/g/h', 'g/./h'],
            ['http://a/b/c/h', 'g/../h'],
            ['http://a/b/c/g;x=1/y', 'g;x=1/./y'],
            ['http://a/b/c/y', 'g;x=1/../y'],
            ['http://a/b/c/g?y/./x', 'g?y/./x'],
            ['http://a/b/c/g?y/../x', 'g?y/../x'],
            ['http://a/b/c/g#s/./x', 'g#s/./x'],
            ['http://a/b/c/g#s/../x', 'g#s/../x'],
            ['http:g', 'http:g'],
            // Empty segments ([RFC3986] does not specify correct behaviour)
            ['http://a/b/c//', './/'],
            ['http://a/b/c///', './//'],
            ['http://a/b//', '..//'],
            ['http://a/b///', '..///'],
            ['http://a/b/c/', './/..'],
            ['http://a/b/c/', './/../'],
            ['http://a/b/c//', './/..//'],
            ['http://a/b/', '..//..'],
            ['http://a/b/', '..//../'],
            ['http://a/b//', '..//..//'],
            ['http://a/b/', '..///../..'],
            ['http://a/b/', '..///../../'],
            ['http://a/b//', '..///../..//'],
            ['http://a//', '/.//'],
            ['http://a///', '/.///'],
            ['http://a/', '/.//..'],
            ['http://a/', '/.//../'],
            ['http://a/', '/.//../..'],
            ['http://a/', '/.//../../'],
        ];

        $baseUri = 'http://a/b/c/d;p?q';

        foreach ($references as [$expected, $reference]) {
            yield $baseUri . ' + ' . $reference =>
                [$expected, $baseUri, $reference];
        }

        yield 'authority + no path' => ['http://a/b/c', 'http://a', 'b/c'];
        yield 'path + no slashes' => ['urn:bar/baz', 'urn:foo', 'bar/baz'];
    }

    /**
     * Implements [RFC3986] Section 5.2.4 ("Remove Dot Segments")
     */
    private static function removeDotSegments(string $path): string
    {
        $in = $path;
        $out = '';
        while ($in !== '') {
            // 2A
            $in = Pcre::replace('@^(?:\.\./|\./)@', '', $in, -1, $count);
            if ($count) {
                continue;
            }
            // 2B
            $in = Pcre::replace('@^/\.(?:/|$)@', '/', $in, -1, $count);
            if ($count) {
                continue;
            }
            // 2C
            $in = Pcre::replace('@^/\.\.(?:/|$)@', '/', $in, -1, $count);
            if ($count) {
                $out = Pcre::replace('@(?:/|^)[^/]*$@', '', $out);
                continue;
            }
            // 2D
            if ($in === '.' || $in === '..') {
                break;
            }
            // 2E
            Pcre::match('@^/?[^/]*@', $in, $matches);
            $out .= $matches[0];
            $in = (string) substr($in, strlen($matches[0]));
        }
        return $out;
    }

    public function testFollowReference(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reference cannot be resolved relative to another reference');
        (new Uri('//'))->follow(new Uri('///'));
    }

    /**
     * @dataProvider parseProvider
     *
     * @param array<string,string|int>|string|int|false|null $expected
     * @param \PHP_URL_SCHEME|\PHP_URL_HOST|\PHP_URL_PORT|\PHP_URL_USER|\PHP_URL_PASS|\PHP_URL_PATH|\PHP_URL_QUERY|\PHP_URL_FRAGMENT|-1|null $component
     */
    public function testParse($expected, string $uri, ?int $component = null, bool $strict = false): void
    {
        $this->assertSame($expected, Uri::parse($uri, $component, $strict));
    }

    /**
     * @dataProvider unparseProvider
     *
     * @param array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string} $parts
     */
    public function testUnparse(string $expected, array $parts): void
    {
        $this->assertSame($expected, Uri::unparse($parts));
    }

    /**
     * @dataProvider unparseProvider
     */
    public function testUnparseWithParseUrl(string $uri): void
    {
        $parts = parse_url($uri);
        $this->assertIsArray($parts);
        $this->assertSame($uri, Uri::unparse($parts));
    }

    /**
     * @return Generator<string,array{string,array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}}>
     */
    public static function unparseProvider(): Generator
    {
        foreach (self::parseProvider() as $test => [$parts, $expected]) {
            if (!is_array($parts)) {
                continue;
            }
            yield $test => [$expected, $parts];
        }
    }

    /**
     * @return Generator<string,array{array{scheme?:string,host?:string,port?:int,user?:string,pass?:string,path?:string,query?:string,fragment?:string}|string|int|false|null,string,2?:int|null,3?:bool}>
     */
    public static function parseProvider(): Generator
    {
        $data = [
            'https://user:pass@host:8443/path;param/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => 'user',
                'pass' => 'pass',
                'path' => '/path;param/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://user:@host:8443/path;param/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => 'user',
                'pass' => '',
                'path' => '/path;param/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://:pass@host:8443/path;param/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'pass' => 'pass',
                'path' => '/path;param/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://:@host:8443/path;param/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'pass' => '',
                'path' => '/path;param/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://@host:8443/path;param/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'path' => '/path;param/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://host:8443/path;param/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'path' => '/path;param/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://user:pass@host:8443/path/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => 'user',
                'pass' => 'pass',
                'path' => '/path/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://user:@host:8443/path/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => 'user',
                'pass' => '',
                'path' => '/path/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://:pass@host:8443/path/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'pass' => 'pass',
                'path' => '/path/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://:@host:8443/path/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'pass' => '',
                'path' => '/path/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://@host:8443/path/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'user' => '',
                'path' => '/path/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://host:8443/path/segment?query#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'path' => '/path/segment',
                'query' => 'query',
                'fragment' => 'fragment',
            ],
            'https://host:8443?query' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'query' => 'query',
            ],
            'https://host?query' => [
                'scheme' => 'https',
                'host' => 'host',
                'query' => 'query',
            ],
            'https://host:8443#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
                'fragment' => 'fragment',
            ],
            'https://host#fragment' => [
                'scheme' => 'https',
                'host' => 'host',
                'fragment' => 'fragment',
            ],
            'https://host:8443' => [
                'scheme' => 'https',
                'host' => 'host',
                'port' => 8443,
            ],
            'https://host' => [
                'scheme' => 'https',
                'host' => 'host',
            ],
            // From https://en.wikipedia.org/wiki/Uniform_Resource_Identifier:
            'https://john.doe@www.example.com:123/forum/questions/?tag=networking&order=newest#top' => [
                'scheme' => 'https',
                'host' => 'www.example.com',
                'port' => 123,
                'user' => 'john.doe',
                'path' => '/forum/questions/',
                'query' => 'tag=networking&order=newest',
                'fragment' => 'top',
            ],
            'ldap://[2001:db8::7]/c=GB?objectClass?one' => [
                'scheme' => 'ldap',
                'host' => '[2001:db8::7]',
                'path' => '/c=GB',
                'query' => 'objectClass?one',
            ],
            'mailto:John.Doe@example.com' => [
                'scheme' => 'mailto',
                'path' => 'John.Doe@example.com',
            ],
            'news:comp.infosystems.www.servers.unix' => [
                'scheme' => 'news',
                'path' => 'comp.infosystems.www.servers.unix',
            ],
            'tel:+1-816-555-1212' => [
                'scheme' => 'tel',
                'path' => '+1-816-555-1212',
            ],
            'telnet://192.0.2.16:80/' => [
                'scheme' => 'telnet',
                'host' => '192.0.2.16',
                'port' => 80,
                'path' => '/',
            ],
            'urn:oasis:names:specification:docbook:dtd:xml:4.1.2' => [
                'scheme' => 'urn',
                'path' => 'oasis:names:specification:docbook:dtd:xml:4.1.2',
            ],
            'foo/bar' => [
                'path' => 'foo/bar',
            ],
            '//example.com:-1' => false,
        ];

        $first = array_key_first($data);
        yield "$first + component -1" => [$data[$first], $first, -1];

        $components = [];
        foreach ($data as $uri => $parts) {
            yield $uri => [$parts, $uri];

            if ($parts === false) {
                if (!isset($components['--path'])) {
                    yield "$uri + path" => [false, $uri, \PHP_URL_PATH];
                    $components['--path'] = true;
                }
                continue;
            }

            // Yield one test per expected component
            foreach ($parts as $component => $value) {
                if (!isset($components[$component])) {
                    yield "$uri + $component" => [$value, $uri, self::COMPONENT_MAP[$component]];
                    $components[$component] = true;
                }
            }

            // And one test per missing component
            $missing = array_diff_key(self::COMPONENT_MAP, $parts);
            foreach ($missing as $component => $value) {
                if (!isset($components["-$component"])) {
                    yield "$uri + $component" => [null, $uri, $value];
                    $components["-$component"] = true;
                }
            }
        }

        $warnings = 0;
        foreach (self::COMPONENT_MAP as $component => $value) {
            if (!isset($components[$component])) {
                fprintf(\STDERR, "WARNING: no test with component '%s' in %s()%s", $component, __METHOD__, \PHP_EOL);
                $warnings++;
            }
            if (!isset($components["-$component"])) {
                fprintf(\STDERR, "WARNING: no test with missing component '%s' in %s()%s", $component, __METHOD__, \PHP_EOL);
                $warnings++;
            }
        }
        if ($warnings) {
            fprintf(\STDERR, '%s', \PHP_EOL);
        }
    }

    public function testParseWithInvalidComponent(): void
    {
        $component = max(self::COMPONENT_MAP) + 1;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid component: $component");
        // @phpstan-ignore-next-line
        Uri::parse('//', $component);
    }

    /**
     * @dataProvider normaliseProvider
     *
     * @param int-mask-of<Uri::EXPAND_EMPTY_PATH|Uri::COLLAPSE_MULTIPLE_SLASHES> $flags
     */
    public function testNormalise(string $expected, string $uri, int $flags = Uri::EXPAND_EMPTY_PATH): void
    {
        $this->assertSame($expected, (string) (new Uri($uri))->normalise($flags));
    }

    /**
     * @return array<array{string,string,2?:int}>
     */
    public static function normaliseProvider(): array
    {
        $collapse = Uri::COLLAPSE_MULTIPLE_SLASHES;
        $expandAndCollapse = Uri::EXPAND_EMPTY_PATH | Uri::COLLAPSE_MULTIPLE_SLASHES;

        return [
            ['urn:', 'urn:'],
            ['http://example.com/', 'http://example.com'],
            ['http://example.com//', 'http://example.com//'],
            ['http://example.com', 'http://example.com', 0],
            ['http://example.com', 'http://example.com', $collapse],
            ['http://example.com/', 'http://example.com', $expandAndCollapse],
            ['http://example.com/', 'http://example.com//', $collapse],
            ['http://example.com/', 'http://example.com//', $expandAndCollapse],
            ['http://a/b/c//?q', 'http://a/b/c/.//?q'],
            ['http://a/b/c///?q', 'http://a/b/c/.///?q'],
            ['http://a/b//?q', 'http://a/b/c/..//?q'],
            ['http://a/b///?q', 'http://a/b/c/..///?q'],
            ['http://a/b/c/?q', 'http://a/b/c/.//..?q'],
            ['http://a/b/c/?q', 'http://a/b/c/.//../?q'],
            ['http://a/b/c//?q', 'http://a/b/c/.//..//?q'],
            ['http://a/b/?q', 'http://a/b/c/..//..?q'],
            ['http://a/b/?q', 'http://a/b/c/..//../?q'],
            ['http://a/b//?q', 'http://a/b/c/..//..//?q'],
            ['http://a/b/?q', 'http://a/b/c/..///../..?q'],
            ['http://a/b/?q', 'http://a/b/c/..///../../?q'],
            ['http://a/b//?q', 'http://a/b/c/..///../..//?q'],
            ['http://a//?q', 'http://a/.//?q'],
            ['http://a///?q', 'http://a/.///?q'],
            ['http://a/?q', 'http://a/.//..?q'],
            ['http://a/?q', 'http://a/.//../?q'],
            ['http://a/?q', 'http://a/.//../..?q'],
            ['http://a/?q', 'http://a/.//../../?q'],
            ['http://a/b/c/?q', 'http://a/b/c/.//?q', $expandAndCollapse],
            ['http://a/b/c/?q', 'http://a/b/c/.///?q', $expandAndCollapse],
            ['http://a/b/?q', 'http://a/b/c/..//?q', $expandAndCollapse],
            ['http://a/b/?q', 'http://a/b/c/..///?q', $expandAndCollapse],
            ['http://a/b/c/?q', 'http://a/b/c/.//..?q', $expandAndCollapse],
            ['http://a/b/c/?q', 'http://a/b/c/.//../?q', $expandAndCollapse],
            ['http://a/b/c/?q', 'http://a/b/c/.//..//?q', $expandAndCollapse],
            ['http://a/b/?q', 'http://a/b/c/..//..?q', $expandAndCollapse],
            ['http://a/b/?q', 'http://a/b/c/..//../?q', $expandAndCollapse],
            ['http://a/b/?q', 'http://a/b/c/..//..//?q', $expandAndCollapse],
            ['http://a/b/?q', 'http://a/b/c/..///../..?q', $expandAndCollapse],
            ['http://a/b/?q', 'http://a/b/c/..///../../?q', $expandAndCollapse],
            ['http://a/b/?q', 'http://a/b/c/..///../..//?q', $expandAndCollapse],
            ['http://a/?q', 'http://a/.//?q', $expandAndCollapse],
            ['http://a/?q', 'http://a/.///?q', $expandAndCollapse],
            ['http://a/?q', 'http://a/.//..?q', $expandAndCollapse],
            ['http://a/?q', 'http://a/.//../?q', $expandAndCollapse],
            ['http://a/?q', 'http://a/.//../..?q', $expandAndCollapse],
            ['http://a/?q', 'http://a/.//../../?q', $expandAndCollapse],
            // [RFC3986] Section 5.2.4 ("Remove Dot Segments")
            ['urn:/a/g', 'urn:/a/b/c/./../../g'],
            ['urn:mid/6', 'urn:mid/content=5/../6'],
        ];
    }

    public function testIsAuthorityForm(): void
    {
        $this->assertTrue(Uri::isAuthorityForm('example.com:80'));
        $this->assertFalse(Uri::isAuthorityForm('example.com'));
        $this->assertFalse(Uri::isAuthorityForm('example.com:80/path'));
        $this->assertFalse(Uri::isAuthorityForm('example.com:80?query'));
        $this->assertFalse(Uri::isAuthorityForm('http://example.com:80'));
        $this->assertFalse(Uri::isAuthorityForm('http://example.com:80/path'));
        $this->assertFalse(Uri::isAuthorityForm('http://example.com:80?query'));
    }
}
