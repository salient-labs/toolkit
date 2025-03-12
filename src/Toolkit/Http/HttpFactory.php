<?php declare(strict_types=1);

namespace Salient\Http;

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
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Utility\File;

/**
 * A PSR-17 HTTP message factory
 */
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
        return new HttpRequest($method, $uri);
    }

    /**
     * @inheritDoc
     */
    public function createResponse(
        int $code = 200,
        string $reasonPhrase = ''
    ): ResponseInterface {
        return new HttpResponse($code, null, null, $reasonPhrase);
    }

    /**
     * @param mixed[] $serverParams
     */
    public function createServerRequest(
        string $method,
        $uri,
        array $serverParams = []
    ): ServerRequestInterface {
        return new HttpServerRequest($method, $uri, $serverParams);
    }

    /**
     * @inheritDoc
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return HttpStream::fromString($content);
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromFile(
        string $filename,
        string $mode = 'r'
    ): StreamInterface {
        return new HttpStream(File::open($filename, $mode));
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return new HttpStream($resource);
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
        return new HttpServerRequestUpload(
            $stream,
            $size,
            $error,
            $clientFilename,
            $clientMediaType
        );
    }

    /**
     * @inheritDoc
     */
    public function createUri(string $uri = ''): PsrUriInterface
    {
        return new Uri($uri);
    }
}
