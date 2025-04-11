<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactoryInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface as PsrServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface as PsrStreamFactoryInterface;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface as PsrUploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface as PsrUriFactoryInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Utility\File;

/**
 * A PSR-17 HTTP message factory
 */
class MessageFactory implements
    PsrRequestFactoryInterface,
    PsrResponseFactoryInterface,
    PsrServerRequestFactoryInterface,
    PsrStreamFactoryInterface,
    PsrUploadedFileFactoryInterface,
    PsrUriFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function createRequest(string $method, $uri): PsrRequestInterface
    {
        return new Request($method, $uri);
    }

    /**
     * @inheritDoc
     */
    public function createResponse(
        int $code = 200,
        string $reasonPhrase = ''
    ): PsrResponseInterface {
        return new Response($code, null, null, $reasonPhrase);
    }

    /**
     * @param mixed[] $serverParams
     */
    public function createServerRequest(
        string $method,
        $uri,
        array $serverParams = []
    ): PsrServerRequestInterface {
        return new ServerRequest($method, $uri, $serverParams);
    }

    /**
     * @inheritDoc
     */
    public function createStream(string $content = ''): PsrStreamInterface
    {
        return Stream::fromString($content);
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromFile(
        string $filename,
        string $mode = 'r'
    ): PsrStreamInterface {
        return new Stream(File::open($filename, $mode));
    }

    /**
     * @inheritDoc
     */
    public function createStreamFromResource($resource): PsrStreamInterface
    {
        return new Stream($resource);
    }

    /**
     * @inheritDoc
     */
    public function createUploadedFile(
        PsrStreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): PsrUploadedFileInterface {
        return new ServerRequestUpload(
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
