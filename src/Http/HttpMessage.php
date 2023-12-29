<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Concern\Immutable;
use Lkrms\Contract\IImmutable;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Exception\InvalidArgumentTypeException;
use Lkrms\Http\Catalog\HttpProtocolVersion;
use Lkrms\Http\Contract\HttpHeadersInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Base class for HTTP messages
 */
abstract class HttpMessage implements MessageInterface, IImmutable
{
    use Immutable {
        withPropertyValue as with;
    }

    protected string $ProtocolVersion;

    protected HttpHeadersInterface $Headers;

    protected StreamInterface $Body;

    /**
     * @param StreamInterface|resource|string|null $body
     * @param HttpHeadersInterface|array<string,string[]|string>|null $headers
     */
    public function __construct(
        $body = null,
        $headers = null,
        string $version = '1.1'
    ) {
        $this->ProtocolVersion = $this->filterProtocolVersion($version);
        $this->Headers = $this->filterHeaders($headers);
        $this->Body = $this->filterBody($body);
    }

    /**
     * @inheritDoc
     */
    public function getProtocolVersion(): string
    {
        return $this->ProtocolVersion;
    }

    /**
     * @inheritDoc
     */
    public function getHeaders(): array
    {
        return $this->Headers->getHeaders();
    }

    /**
     * @inheritDoc
     */
    public function hasHeader(string $name): bool
    {
        return $this->Headers->hasHeader($name);
    }

    /**
     * @inheritDoc
     */
    public function getHeader(string $name): array
    {
        return $this->Headers->getHeader($name);
    }

    /**
     * @inheritDoc
     */
    public function getHeaderLine(string $name): string
    {
        return $this->Headers->getHeaderLine($name);
    }

    /**
     * @inheritDoc
     */
    public function getBody(): StreamInterface
    {
        return $this->Body;
    }

    /**
     * @inheritDoc
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        return $this->with('ProtocolVersion', $this->filterProtocolVersion($version));
    }

    /**
     * @inheritDoc
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        return $this->with('Headers', $this->Headers->set($name, $value));
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        return $this->with('Headers', $this->Headers->add($name, $value));
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader(string $name): MessageInterface
    {
        return $this->with('Headers', $this->Headers->unset($name));
    }

    /**
     * @inheritDoc
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        return $this->with('Body', $body);
    }

    private function filterProtocolVersion(string $version): string
    {
        if (!HttpProtocolVersion::hasValue($version)) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP protocol version: %s', $version)
            );
        }
        return $version;
    }

    /**
     * @param HttpHeadersInterface|array<string,string[]>|null $headers
     */
    private function filterHeaders($headers): HttpHeadersInterface
    {
        if ($headers instanceof HttpHeadersInterface) {
            return $headers;
        }
        if (is_array($headers) || $headers === null) {
            return new HttpHeaders($headers ?: []);
        }
        // @phpstan-ignore-next-line
        throw new InvalidArgumentTypeException(
            1,
            'headers',
            'HttpHeadersInterface|array<string,string[]>|null',
            $headers
        );
    }

    /**
     * @param StreamInterface|resource|string|null $body
     */
    private function filterBody($body): StreamInterface
    {
        if ($body instanceof StreamInterface) {
            return $body;
        }
        if (is_string($body) || $body === null) {
            return Stream::fromString((string) $body);
        }
        try {
            return new Stream($body);
        } catch (InvalidArgumentException $ex) {
            throw new InvalidArgumentTypeException(
                1,
                'body',
                'StreamInterface|resource|string|null',
                $body
            );
        }
    }
}
