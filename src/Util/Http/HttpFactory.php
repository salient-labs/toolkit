<?php declare(strict_types=1);

namespace Lkrms\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Salient\Core\Exception\MethodNotImplementedException;
use Salient\Core\Utility\File;

class HttpFactory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new HttpRequest($uri, $method);
    }

    /**
     * @inheritDoc
     */
    public function createResponse(
        int $code = 200,
        string $reasonPhrase = ''
    ): ResponseInterface {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            ResponseFactoryInterface::class
        );
    }

    /**
     * @param array<string,mixed> $serverParams
     */
    public function createServerRequest(
        string $method,
        $uri,
        array $serverParams = []
    ): ServerRequestInterface {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            ServerRequestFactoryInterface::class
        );
    }

    /**
     * @inheritDoc
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return Stream::fromString($content);
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromFile(
        string $filename,
        string $mode = 'r'
    ): StreamInterface {
        return new Stream(File::open($filename, $mode));
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }

    /**
     * @inheritDoc
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            UploadedFileFactoryInterface::class
        );
    }

    /**
     * @inheritDoc
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return (new Uri($uri, false))->normalise();
    }
}
