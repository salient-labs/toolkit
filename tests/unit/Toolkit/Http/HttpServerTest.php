<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Catalog\MimeType;
use Salient\Contract\Http\HttpHeader as Header;
use Salient\Contract\Http\HttpRequestMethod as Method;
use Salient\Contract\Http\HttpResponseInterface as ResponseInterface;
use Salient\Contract\Http\HttpServerRequestInterface as ServerRequest;
use Salient\Core\Facade\Console;
use Salient\Core\Process;
use Salient\Http\HttpResponse as Response;
use Salient\Http\HttpServer;
use Salient\Tests\TestCase;
use Salient\Utility\Str;

/**
 * @covers \Salient\Http\HttpServer
 * @covers \Salient\Http\HttpServerRequest
 * @covers \Salient\Http\HttpRequest
 * @covers \Salient\Http\HttpResponse
 * @covers \Salient\Http\AbstractHttpMessage
 * @covers \Salient\Http\HasHttpHeaders
 * @covers \Salient\Http\HttpHeaders
 */
final class HttpServerTest extends TestCase
{
    private HttpServer $Server;

    public function testConstructor(): void
    {
        $server = new HttpServer('localhost', 8080, 300);
        $this->assertSame('localhost', $server->getHost());
        $this->assertSame(8080, $server->getPort());
        $this->assertSame(300, $server->getTimeout());
        $this->assertFalse($server->hasProxy());
        $this->assertSame('http://localhost:8080', $server->getBaseUri());
        $this->assertSame('http', $server->getScheme());
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
        $this->assertTrue($proxied->getProxyTls());
        $this->assertSame('', $proxied->getProxyBasePath());
        $this->assertSame('https://example.com', $proxied->getBaseUri());
        $this->assertSame('https', $proxied->getScheme());
        $this->assertSame($proxied, $proxied->withProxy('example.com', 443));

        $notProxied = $proxied->withoutProxy();
        $this->assertNotSame($proxied, $notProxied);
        $this->assertNotSame($server, $notProxied);
        $this->assertFalse($notProxied->hasProxy());
        $this->assertSame('http://localhost:8080', $notProxied->getBaseUri());
        $this->assertSame('http', $notProxied->getScheme());

        $proxied = $server->withProxy('example.com', 8443, true);
        $this->assertSame(8443, $proxied->getProxyPort());
        $this->assertTrue($proxied->getProxyTls());
        $this->assertSame('https://example.com:8443', $proxied->getBaseUri());

        $proxied = $server->withProxy('example.com', 8080);
        $this->assertSame(8080, $proxied->getProxyPort());
        $this->assertFalse($proxied->getProxyTls());
        $this->assertSame('http://example.com:8080', $proxied->getBaseUri());

        $proxied = $server->withProxy('example.com', 80, false, '/api');
        $this->assertSame('/api', $proxied->getProxyBasePath());
        $this->assertSame('http://example.com/api', $proxied->getBaseUri());

        $proxied = $server->withProxy('example.com', 80, true, 'api');
        $this->assertSame('/api', $proxied->getProxyBasePath());
        $this->assertSame('https://example.com:80/api', $proxied->getBaseUri());

        $proxied = $server->withProxy('example.com', 443, false, '/api/');
        $this->assertSame('/api', $proxied->getProxyBasePath());
        $this->assertSame('http://example.com:443/api', $proxied->getBaseUri());

        $server = new HttpServer('localhost', 80);
        $this->assertSame(80, $server->getPort());
        $this->assertSame(-1, $server->getTimeout());
        $this->assertSame('http://localhost', $server->getBaseUri());
    }

    public function testListen(): void
    {
        $server = $this->getServerWithClient($client);
        $this->assertTrue($server->isRunning());

        /** @var ServerRequest */
        $request = $server->listen(
            function (ServerRequest $request, bool &$continue, &$return): ResponseInterface {
                $return = $request;
                return new Response(
                    200,
                    'Hello, world!',
                    [Header::CONTENT_TYPE => MimeType::TEXT],
                );
            },
        );
        $this->assertSame(0, $client->wait());
        $this->assertInstanceOf(ServerRequest::class, $request);
        $this->assertSame(Method::GET, $request->getMethod());
        $this->assertSame('/', $request->getRequestTarget());
        $this->assertSame([
            'Host' => ['localhost:3008'],
            'Accept' => ['*/*'],
        ], $request->getHeaders());
        $this->assertSame('http://localhost:3008/', (string) $request->getUri());
        $this->assertSame('', (string) $request->getBody());
        $this->assertSame(Str::setEol(<<<'EOF'
HTTP/1.1 200 OK
Content-Type: text/plain

Hello, world!
EOF, "\r\n"), $client->getOutputAsText());
        $this->assertSame(<<<'EOF'
==> Connected to localhost:3008
> GET / HTTP/1.1
> Host: localhost:3008
> Accept: */*
>
EOF, $client->getOutputAsText(Process::ERR));
    }

    private function getServerWithClient(
        ?Process &$client,
        string $method = Method::GET,
        string $target = '/',
        string $body = '',
        string ...$headers
    ): HttpServer {
        $this->Server ??= new HttpServer('localhost', 3008, 30);
        if (!$this->Server->isRunning()) {
            $this->Server->start();
        }

        $client = (
            new Process([
                ...self::PHP_COMMAND,
                self::getPackagePath() . '/tests/unit/Toolkit/http-client.php',
                'localhost:3008',
                $method,
                $target,
                '300',
                ...$headers,
            ], $body)
        )->start();

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
