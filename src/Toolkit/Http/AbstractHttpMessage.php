<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpMessageInterface;
use Salient\Contract\Http\HttpMultipartStreamInterface;
use Salient\Contract\Http\MimeType;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\Regex;
use InvalidArgumentException;

/**
 * Base class for PSR-7 HTTP message classes
 *
 * @api
 */
abstract class AbstractHttpMessage implements HttpMessageInterface
{
    use HasHttpHeaders;
    use ImmutableTrait;

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
    public function getBody(): StreamInterface
    {
        return $this->Body;
    }

    /**
     * @inheritDoc
     */
    public function getHttpPayload(bool $withoutBody = false): string
    {
        $message = implode("\r\n", [
            $this->getStartLine(),
            (string) $this->Headers,
            '',
            '',
        ]);

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
     * @param StreamInterface|resource|string|null $body
     */
    public function withBody($body): MessageInterface
    {
        return $this
            ->with('Body', $this->filterBody($body))
            ->maybeSetContentType();
    }

    private function filterProtocolVersion(string $version): string
    {
        if (!Regex::match('/^[0-9](?:\.[0-9])?$/D', $version)) {
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
                    HttpUtil::maybeQuoteString($this->Body->getBoundary()),
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
     * @return array{httpVersion:string,cookies:array<array{name:string,value:string,path?:string,domain?:string,expires?:string,httpOnly?:bool,secure?:bool}>,headers:array<array{name:string,value:string}>,headersSize:int,bodySize:int}
     */
    public function jsonSerialize(): array
    {
        return [
            'httpVersion' => sprintf('HTTP/%s', $this->ProtocolVersion),
            'cookies' => [],
            'headers' => $this->Headers->jsonSerialize(),
            'headersSize' => strlen($this->getHttpPayload(true)),
            'bodySize' => $this->Body->getSize() ?? -1,
        ];
    }
}
