<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Http\Message\MessageInterface;
use Salient\Contract\Http\Message\MultipartStreamInterface;
use Salient\Contract\Http\HeadersInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Utility\Exception\InvalidArgumentTypeException;
use Salient\Utility\Regex;
use InvalidArgumentException;

/**
 * Base class for PSR-7 HTTP message classes
 */
abstract class AbstractHttpMessage implements MessageInterface
{
    use HasHttpHeaders;
    use ImmutableTrait;

    protected string $ProtocolVersion;
    protected HeadersInterface $Headers;
    protected PsrStreamInterface $Body;

    /**
     * Get the start line of the message
     */
    abstract protected function getStartLine(): string;

    /**
     * @param PsrStreamInterface|resource|string|null $body
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
    public function getBody(): PsrStreamInterface
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
    public function withProtocolVersion(string $version): PsrMessageInterface
    {
        return $this->with('ProtocolVersion', $this->filterProtocolVersion($version));
    }

    /**
     * @param PsrStreamInterface|resource|string|null $body
     */
    public function withBody($body): PsrMessageInterface
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
    private function filterHeaders($headers): HeadersInterface
    {
        if ($headers instanceof HeadersInterface) {
            return $headers;
        }
        return new HttpHeaders($headers ?? []);
    }

    /**
     * @param PsrStreamInterface|resource|string|null $body
     */
    private function filterBody($body): PsrStreamInterface
    {
        if ($body instanceof PsrStreamInterface) {
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
                PsrStreamInterface::class . '|resource|string|null',
                $body
            );
        }
    }

    /**
     * @return $this
     */
    private function maybeSetContentType(): self
    {
        if ($this->Body instanceof MultipartStreamInterface) {
            $this->Headers = $this->Headers->set(
                self::HEADER_CONTENT_TYPE,
                sprintf(
                    '%s; boundary=%s',
                    self::TYPE_FORM_MULTIPART,
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
