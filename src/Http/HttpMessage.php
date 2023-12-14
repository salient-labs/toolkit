<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Concern\Immutable;
use Lkrms\Contract\IImmutable;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Exception\InvalidArgumentTypeException;
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
     * @param HttpHeadersInterface|array<string,string[]>|null $headers
     */
    public function __construct(
        $body = null,
        $headers = null,
        string $protocolVersion = '1.1'
    ) {
        $this->ProtocolVersion = $protocolVersion;

        if ($headers instanceof HttpHeadersInterface) {
            $this->Headers = $headers;
        } elseif (is_array($headers) || $headers === null) {
            $this->Headers = new HttpHeaders($headers ?: []);
        } else {
            throw new InvalidArgumentTypeException(2, 'headers', 'HttpHeadersInterface|array<string,string[]>|null', $headers);
        }

        if ($body instanceof StreamInterface) {
            $this->Body = $body;
        } elseif (is_string($body) || $body === null) {
            $this->Body = Stream::fromContents((string) $body);
        } else {
            try {
                $this->Body = new Stream($body);
            } catch (InvalidArgumentException $ex) {
                throw new InvalidArgumentTypeException(1, 'body', 'StreamInterface|resource|string|null', $body);
            }
        }
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
    public function withProtocolVersion(string $version): self
    {
        return $this->with('ProtocolVersion', $version);
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
    public function withHeader(string $name, $value): self
    {
        return $this->with('Headers', $this->Headers->set($name, $value));
    }

    /**
     * @inheritDoc
     */
    public function withAddedHeader(string $name, $value): self
    {
        return $this->with('Headers', $this->Headers->add($name, $value));
    }

    /**
     * @inheritDoc
     */
    public function withoutHeader(string $name): self
    {
        return $this->with('Headers', $this->Headers->unset($name));
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
    public function withBody(StreamInterface $body): self
    {
        return $this->with('Body', $body);
    }
}
