<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\MimeType;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpMessageInterface;
use Salient\Contract\Http\HttpMultipartStreamInterface;
use Salient\Contract\Http\HttpProtocolVersion;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\InvalidArgumentTypeException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Http;

/**
 * Base class for HTTP messages
 *
 * @api
 */
abstract class HttpMessage implements HttpMessageInterface
{
    use HasImmutableProperties {
        withPropertyValue as with;
    }

    protected string $ProtocolVersion;
    protected HttpHeadersInterface $Headers;
    protected StreamInterface $Body;

    /**
     * Get the start line of the message
     */
    abstract protected function getStartLine(): string;

    /**
     * @param StreamInterface|resource|string|null $body
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $headers
     */
    public function __construct(
        $body = null,
        $headers = null,
        string $version = '1.1'
    ) {
        $this->ProtocolVersion = $this->filterProtocolVersion($version);
        $this->Headers = $this->filterHeaders($headers);
        $this->Body = $this->filterBody($body);

        $this->maybeSetContentType();
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
    public function getHttpPayload(bool $withoutBody = false): string
    {
        return $this
            ->withContentLength()
            ->doGetHttpPayload($withoutBody);
    }

    private function doGetHttpPayload(bool $withoutBody): string
    {
        $message = implode("\r\n", Arr::push(
            Arr::unshift(
                $this->Headers->getLines(),
                $this->getStartLine(),
            ),
            '',
            '',
        ));

        return $withoutBody
            ? $message
            : $message . $this->Body;
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
     * @param StreamInterface|resource|string|null $body
     */
    public function withBody($body): MessageInterface
    {
        return $this
            ->with('Body', $this->filterBody($body))
            ->maybeSetContentType();
    }

    /**
     * @inheritDoc
     */
    public function withContentLength(): HttpMessageInterface
    {
        $size = $this->Body->getSize();
        if ($size !== null) {
            return $this->withHeader(HttpHeader::CONTENT_LENGTH, (string) $size);
        }
        // @codeCoverageIgnoreStart
        return $this->withoutHeader(HttpHeader::CONTENT_LENGTH);
        // @codeCoverageIgnoreEnd
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
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $headers
     */
    private function filterHeaders($headers): HttpHeadersInterface
    {
        if ($headers instanceof HttpHeadersInterface) {
            return $headers;
        }
        return new HttpHeaders($headers ?? []);
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
            return HttpStream::fromString((string) $body);
        }
        try {
            return new HttpStream($body);
        } catch (InvalidArgumentException $ex) {
            throw new InvalidArgumentTypeException(
                1,
                'body',
                'StreamInterface|resource|string|null',
                $body
            );
        }
    }

    /**
     * @return $this
     */
    private function maybeSetContentType(): self
    {
        if ($this->Body instanceof HttpMultipartStreamInterface) {
            $this->Headers = $this->Headers->set(
                HttpHeader::CONTENT_TYPE,
                sprintf(
                    '%s; boundary=%s',
                    MimeType::FORM_MULTIPART,
                    Http::getQuotedString($this->Body->getBoundary()),
                ),
            );
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->getHttpPayload();
    }

    /**
     * @return array{httpVersion:string,headers:array<array{name:string,value:string}>,...}
     */
    public function jsonSerialize(): array
    {
        return [
            'httpVersion' => sprintf('HTTP/%s', $this->ProtocolVersion),
            'headers' => $this->Headers->jsonSerialize(),
        ];
    }
}
