<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;

/**
 * @api
 *
 * @extends AbstractRequest<PsrRequestInterface>
 */
class Request extends AbstractRequest
{
    /**
     * @api
     */
    final public function __construct(
        string $method,
        $uri,
        $body = null,
        $headers = null,
        ?string $requestTarget = null,
        string $version = '1.1'
    ) {
        parent::__construct($method, $uri, $body, $headers, $requestTarget, $version);
    }

    /**
     * @inheritDoc
     */
    public static function fromPsr7(PsrMessageInterface $message): Request
    {
        return $message instanceof static
            ? $message
            : new static(
                $message->getMethod(),
                $message->getUri(),
                $message->getBody(),
                $message->getHeaders(),
                $message->getRequestTarget(),
                $message->getProtocolVersion(),
            );
    }
}
