<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFileInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Http\MessageFactory;
use Salient\Tests\TestCase;
use Salient\Utility\File;

/**
 * @covers \Salient\Http\MessageFactory
 */
final class HttpFactoryTest extends TestCase
{
    private MessageFactory $Factory;

    public function testCreateRequest(): void
    {
        $request = $this->Factory->createRequest('GET', 'http://example.com');
        $this->assertInstanceOf(PsrRequestInterface::class, $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http://example.com', (string) $request->getUri());
    }

    public function testCreateResponse(): void
    {
        $response = $this->Factory->createResponse(200, 'OK');
        $this->assertInstanceOf(PsrResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getReasonPhrase());
    }

    public function testCreateServerRequest(): void
    {
        $request = $this->Factory->createServerRequest('GET', 'http://example.com', ['FOO' => 'bar']);
        $this->assertInstanceOf(PsrServerRequestInterface::class, $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('http://example.com', (string) $request->getUri());
        $this->assertSame(['FOO' => 'bar'], $request->getServerParams());
    }

    public function testCreateStream(): void
    {
        $stream = $this->Factory->createStream('content');
        $this->assertInstanceOf(PsrStreamInterface::class, $stream);
        $this->assertSame('content', (string) $stream);
    }

    public function testCreateStreamFromFile(): void
    {
        $stream = $this->Factory->createStreamFromFile(__FILE__);
        $this->assertInstanceOf(PsrStreamInterface::class, $stream);
        $this->assertSame(File::getContents(__FILE__), (string) $stream);
    }

    public function testCreateStreamFromResource(): void
    {
        $resource = File::open('php://memory', 'r+');
        File::write($resource, 'content');
        $stream = $this->Factory->createStreamFromResource($resource);
        $this->assertInstanceOf(PsrStreamInterface::class, $stream);
        $this->assertSame('content', (string) $stream);
    }

    public function testCreateUploadedFile(): void
    {
        $file = $this->Factory->createUploadedFile(
            $stream = $this->Factory->createStream('content'),
            $size = $stream->getSize(),
            $error = \UPLOAD_ERR_OK,
            $clientFilename = 'filename.txt',
            $clientMediaType = 'text/plain',
        );
        $this->assertInstanceOf(PsrUploadedFileInterface::class, $file);
        $this->assertSame($stream, $file->getStream());
        $this->assertSame($size, $file->getSize());
        $this->assertSame($error, $file->getError());
        $this->assertSame($clientFilename, $file->getClientFilename());
        $this->assertSame($clientMediaType, $file->getClientMediaType());
    }

    public function testCreateUri(): void
    {
        $uri = $this->Factory->createUri('http://example.com');
        $this->assertInstanceOf(PsrUriInterface::class, $uri);
        $this->assertSame('http://example.com', (string) $uri);
    }

    protected function setUp(): void
    {
        $this->Factory = new MessageFactory();
    }
}
