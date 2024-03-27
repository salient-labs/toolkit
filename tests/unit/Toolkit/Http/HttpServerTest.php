<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Http\HttpServer;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Http\HttpServer
 */
final class HttpServerTest extends TestCase
{
    public function testConstructor(): void
    {
        $server = new HttpServer('localhost', 8080, 300);
        $this->assertSame('localhost', $server->getHost());
        $this->assertSame(8080, $server->getPort());
        $this->assertSame(300, $server->getTimeout());
        $this->assertFalse($server->hasProxy());
        $this->assertSame('http://localhost:8080', $server->getBaseUri());

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
}
