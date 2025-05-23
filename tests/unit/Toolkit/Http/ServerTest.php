<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Http\Message\ServerRequestInterface;
use Salient\Contract\Http\HasHttpHeader;
use Salient\Contract\Http\HasMediaType;
use Salient\Contract\Http\HasRequestMethod;
use Salient\Core\Facade\Console;
use Salient\Core\Process;
use Salient\Http\Server\Server;
use Salient\Http\Server\ServerResponse;
use Salient\Tests\TestCase;
use Salient\Utility\Str;

/**
 * @covers \Salient\Http\Server\Server
 * @covers \Salient\Http\Message\ServerRequest
 * @covers \Salient\Http\Message\Request
 * @covers \Salient\Http\Message\AbstractRequest
 * @covers \Salient\Http\Message\Response
 * @covers \Salient\Http\Message\AbstractMessage
 * @covers \Salient\Http\HasInnerHeadersTrait
 * @covers \Salient\Http\Headers
 */
final class ServerTest extends TestCase implements HasHttpHeader, HasMediaType, HasRequestMethod
{
    private Server $Server;

    public function testConstructor(): void
    {
        $server = new Server('localhost', 8080, 300);
        $this->assertSame('localhost', $server->getHost());
        $this->assertSame(8080, $server->getPort());
        $this->assertSame(300, $server->getTimeout());
        $this->assertFalse($server->hasProxy());
        $this->assertSame('http://localhost:8080', (string) $server->getUri());
        $this->assertFalse($server->isRunning());
        $this->assertSame($server, $server->withoutProxy());

        $proxied = $server->withProxy('example.com', 443);
        $this->assertNotSame($server, $proxied);
        $this->assertSame('localhost', $proxied->getHost());
        $this->assertSame(8080, $proxied->getPort());
        $this->assertSame(300, $proxied->getTimeout());
        $this->assertTrue($proxied->hasProxy());
        $this->assertSame('example.com', $proxied->getProxyHost());
        $this->assertSame(443, $proxied->getProxyPort());
        $this->assertTrue($proxied->proxyHasTls());
        $this->assertSame('', $proxied->getProxyPath());
        $this->assertSame('https://example.com', (string) $proxied->getUri());
        $this->assertSame($proxied, $proxied->withProxy('example.com', 443));

        $notProxied = $proxied->withoutProxy();
        $this->assertNotSame($proxied, $notProxied);
        $this->assertNotSame($server, $notProxied);
        $this->assertFalse($notProxied->hasProxy());
        $this->assertSame('http://localhost:8080', (string) $notProxied->getUri());

        $proxied = $server->withProxy('example.com', 8443, true);
        $this->assertSame(8443, $proxied->getProxyPort());
        $this->assertTrue($proxied->proxyHasTls());
        $this->assertSame('https://example.com:8443', (string) $proxied->getUri());

        $proxied = $server->withProxy('example.com', 8080);
        $this->assertSame(8080, $proxied->getProxyPort());
        $this->assertFalse($proxied->proxyHasTls());
        $this->assertSame('http://example.com:8080', (string) $proxied->getUri());

        $proxied = $server->withProxy('example.com', 80, false, '/api');
        $this->assertSame('/api', $proxied->getProxyPath());
        $this->assertSame('http://example.com/api', (string) $proxied->getUri());

        $proxied = $server->withProxy('example.com', 80, true, 'api');
        $this->assertSame('/api', $proxied->getProxyPath());
        $this->assertSame('https://example.com:80/api', (string) $proxied->getUri());

        $proxied = $server->withProxy('example.com', 443, false, '/api/');
        $this->assertSame('/api', $proxied->getProxyPath());
        $this->assertSame('http://example.com:443/api', (string) $proxied->getUri());

        $server = new Server('localhost', 80);
        $this->assertSame(80, $server->getPort());
        $this->assertSame(-1, $server->getTimeout());
        $this->assertSame('http://localhost', (string) $server->getUri());
    }

    public function testListen(): void
    {
        $server = $this->getServerWithClient(
            $client,
            [
                ['RESET', '/resource/71', self::HEADER_AUTHORIZATION . ': Bearer foo.bar.baz'],
                [],
            ],
            '{}',
            true,
        );
        $this->assertTrue($server->isRunning());

        /** @var ServerRequestInterface */
        $request = $server->listen(
            function (ServerRequestInterface $request): ServerResponse {
                return (new ServerResponse(
                    200,
                    'Hello, world!',
                    [self::HEADER_CONTENT_TYPE => self::TYPE_TEXT],
                ))->withReturnValue($request);
            },
            2,
        );
        $this->assertSame(0, $client->wait());
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertSame(self::METHOD_GET, $request->getMethod());
        $this->assertSame('/', $request->getRequestTarget());
        $this->assertSame([
            'Host' => ['localhost:3008'],
            'Accept' => ['*/*'],
        ], $request->getHeaders());
        $this->assertSame('http://localhost:3008/', (string) $request->getUri());
        $this->assertSame('', (string) $request->getBody());
        $this->assertSame(Str::setEol(<<<'EOF'
HTTP/1.1 501 Not Implemented

Invalid HTTP request line: RESET /resource/71 HTTP/1.1

HTTP/1.1 200 OK
Content-Type: text/plain

Hello, world!
EOF, "\r\n"), $client->getOutputAsText());
        $this->assertSame(<<<'EOF'
==> Connected to localhost:3008
 -> Sending request #1 (122 bytes)
> RESET /resource/71 HTTP/1.1
> Host: localhost:3008
> Accept: */*
> Authorization: Bearer foo.bar.baz
> Content-Length: 2
>
==> Connected to localhost:3008
 -> Sending request #2 (53 bytes)
> GET / HTTP/1.1
> Host: localhost:3008
> Accept: */*
>
EOF, $client->getOutputAsText(Process::STDERR));
    }

    /**
     * @param array<list<string>> $requests An array of lists with zero or more
     * of the following values:
     * - method (default: `"GET"`)
     * - target (default: `"/"`)
     * - `"<header>: <value>"`, ...
     * @param-out Process $client
     */
    private function getServerWithClient(
        ?Process &$client,
        array $requests = [],
        string $body = '',
        bool $addNewlines = false
    ): Server {
        $this->Server ??= new Server('localhost', 3008, 30);
        if (!$this->Server->isRunning()) {
            $this->Server->start();
        }

        $args = [
            ...self::PHP_COMMAND,
            dirname(__DIR__) . '/http-client.php',
            ...($addNewlines ? ['--add-newlines'] : []),
            'localhost:3008',
            '300',
        ];

        $i = 0;
        foreach ($requests as $request) {
            if ($i++) {
                $args[] = '--';
            }
            $args[] = array_shift($request) ?? self::METHOD_GET;
            $args[] = array_shift($request) ?? '/';
            if ($request) {
                array_push($args, ...$request);
            }
        }

        $client = (new Process($args, $body))->start();

        return $this->Server;
    }

    protected function tearDown(): void
    {
        if (isset($this->Server)) {
            if ($this->Server->isRunning()) {
                $this->Server->stop();
            }
        }
        Console::unload();
    }
}
