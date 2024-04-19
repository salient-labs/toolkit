<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\MessageInterface;
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
     * Get the HTTP headers of the message
     */
    public function getHttpHeaders(): HttpHeadersInterface;

    /**
     * Get the message as an HTTP payload
     */
    public function getHttpPayload(bool $withoutBody = false): string;

    /**
     * Get the message as an HTTP Archive (HAR) object
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array;
}
