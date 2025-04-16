<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Http\Message\MessageInterface;
use Salient\Contract\Http\HeadersInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Http\HasInnerHeadersTrait;
use Salient\Http\Headers;
use Salient\Utility\Arr;
use Salient\Utility\Regex;
use InvalidArgumentException;

/**
 * @internal
 *
 * @template TPsr7 of PsrMessageInterface
 *
 * @implements MessageInterface<TPsr7>
 */
abstract class AbstractMessage implements MessageInterface
{
    use HasBody;
    use HasInnerHeadersTrait;
    use ImmutableTrait;

    protected string $ProtocolVersion;
    protected PsrStreamInterface $Body;

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
    public function withProtocolVersion(string $version): PsrMessageInterface
    {
        return $this->with('ProtocolVersion', $this->filterProtocolVersion($version));
    }

    /**
     * @param PsrStreamInterface|resource|string|null $body
     */
    public function withBody($body): PsrMessageInterface
    {
        return $this->with('Body', $this->filterBody($body));
    }

    private function filterProtocolVersion(string $version): string
    {
        if (!Regex::match('/^[0-9](?:\.[0-9])?$/D', $version)) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP protocol version: %s', $version),
            );
        }
        return $version;
    }

    /**
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $headers
     */
    private function filterHeaders($headers): HeadersInterface
    {
        return $headers instanceof HeadersInterface
            ? $headers
            : new Headers($headers ?? []);
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return Arr::implode("\r\n", [
            $this->getStartLine(),
            (string) $this->Headers->normalise(),
        ], '') . "\r\n\r\n" . $this->Body;
    }

    /**
     * @inheritDoc
     *
     * @return array{httpVersion:string,cookies:array<array{name:string,value:string,path?:string,domain?:string,expires?:string,httpOnly?:bool,secure?:bool}>,headers:array<array{name:string,value:string}>,headersSize:int,bodySize:int}
     */
    public function jsonSerialize(): array
    {
        return [
            'httpVersion' => 'HTTP/' . $this->ProtocolVersion,
            'cookies' => [],
            'headers' => $this->Headers->jsonSerialize(),
            'headersSize' => strlen((string) $this->withBody(null)),
            'bodySize' => $this->Body->getSize() ?? -1,
        ];
    }

    /**
     * @internal
     */
    abstract protected function getStartLine(): string;
}
