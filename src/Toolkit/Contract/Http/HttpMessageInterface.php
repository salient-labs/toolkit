<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\MessageInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Immutable;
use JsonSerializable;
use Stringable;

/**
 * @api
 */
interface HttpMessageInterface extends
    MessageInterface,
    Stringable,
    JsonSerializable,
    Immutable
{
    /**
     * Get an instance with the given headers
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $headers
     * @return static
     */
    public function withHeaders($headers): HttpMessageInterface;

    /**
     * Get an instance where the given headers are merged with existing headers
     *
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string> $headers
     * @return static
     */
    public function withMergedHeaders($headers, bool $addToExisting = false): HttpMessageInterface;

    /**
     * Get an instance where the size of the message body is applied to the
     * Content-Length header
     *
     * @return static
     */
    public function withContentLength(): HttpMessageInterface;

    /**
     * Get the message as an HTTP payload
     */
    public function getHttpPayload(bool $withoutBody = false): string;

    /**
     * Get the message as an HTTP payload
     */
    public function __toString(): string;

    /**
     * Get the message as an HTTP Archive (HAR) object
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array;
}
