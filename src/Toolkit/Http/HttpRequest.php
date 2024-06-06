<?php declare(strict_types=1);

namespace Salient\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpRequestInterface;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Utility\Exception\InvalidArgumentTypeException;
use Salient\Core\Utility\Regex;
use Stringable;

/**
 * A PSR-7 request (outgoing, client-side)
 *
 * @api
 */
class HttpRequest extends AbstractHttpMessage implements HttpRequestInterface
{
    private const TOKEN = '/^[-0-9a-z!#$%&\'*+.^_`|~]++$/iD';

    protected string $Method;
    protected ?string $RequestTarget;
    protected Uri $Uri;

    /**
     * Creates a new HttpRequest object
     *
     * @param PsrUriInterface|Stringable|string $uri
     * @param StreamInterface|resource|string|null $body
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $headers
     */
    public function __construct(
        string $method,
        $uri,
        $body = null,
        $headers = null,
        ?string $requestTarget = null,
        string $version = '1.1'
    ) {
        $this->Method = $this->filterMethod($method);
        $this->RequestTarget = $this->filterRequestTarget($requestTarget);
        $this->Uri = $this->filterUri($uri);

        parent::__construct($body, $headers, $version);

        if ($this->Headers->getHeaderLine(HttpHeader::HOST) !== '') {
            return;
        }
        $host = $this->getUriHost();
        if ($host === '') {
            return;
        }
        $this->Headers = $this->Headers->set(HttpHeader::HOST, $host);
    }

    /**
     * @inheritDoc
     */
    public static function fromPsr7(MessageInterface $message): HttpRequest
    {
        if ($message instanceof HttpRequest) {
            return $message;
        }

        if (!$message instanceof RequestInterface) {
            throw new InvalidArgumentTypeException(1, 'message', RequestInterface::class, $message);
        }

        return new self(
            $message->getMethod(),
            $message->getUri(),
            $message->getBody(),
            $message->getHeaders(),
            $message->getRequestTarget(),
            $message->getProtocolVersion(),
        );
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->Method;
    }

    /**
     * @inheritDoc
     */
    public function getRequestTarget(): string
    {
        if ($this->RequestTarget !== null) {
            return $this->RequestTarget;
        }

        $target = $this->Uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        $query = $this->Uri->toParts()['query'] ?? null;
        if ($query !== null) {
            return "{$target}?{$query}";
        }

        return $target;
    }

    /**
     * @inheritDoc
     */
    public function getUri(): Uri
    {
        return $this->Uri;
    }

    /**
     * @inheritDoc
     */
    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        return $this->with('RequestTarget', $this->filterRequestTarget($requestTarget));
    }

    /**
     * @inheritDoc
     */
    public function withMethod(string $method): RequestInterface
    {
        return $this->with('Method', $this->filterMethod($method));
    }

    /**
     * @inheritDoc
     */
    public function withUri(PsrUriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        if ((string) $uri === (string) $this->Uri) {
            $instance = $this;
        } else {
            $instance = $this->with('Uri', $this->filterUri($uri));
        }

        if (
            $preserveHost
            && $instance->Headers->getHeaderLine(HttpHeader::HOST) !== ''
        ) {
            return $instance;
        }

        $host = $instance->getUriHost();
        if ($host === '') {
            return $instance;
        }
        return $instance->withHeader(HttpHeader::HOST, $host);
    }

    /**
     * @inheritDoc
     */
    protected function getStartLine(): string
    {
        return sprintf(
            '%s %s HTTP/%s',
            $this->Method,
            $this->getRequestTarget(),
            $this->ProtocolVersion,
        );
    }

    private function getUriHost(): string
    {
        $host = $this->Uri->getHost();
        if ($host === '') {
            return '';
        }

        $port = $this->Uri->getPort();
        if ($port !== null) {
            $host .= ':' . $port;
        }

        return $host;
    }

    private function filterMethod(string $method): string
    {
        if (!Regex::match(self::TOKEN, $method)) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP method: %s', $method)
            );
        }
        return $method;
    }

    /**
     * Validate a request target as per [RFC7230] Section 5.3
     */
    private function filterRequestTarget(?string $requestTarget): ?string
    {
        if ($requestTarget === null || $requestTarget === '') {
            return null;
        }

        // "asterisk-form"
        if ($requestTarget === '*') {
            return $requestTarget;
        }

        // "authority-form"
        if (Uri::isAuthorityForm($requestTarget)) {
            return $requestTarget;
        }

        $parts = Uri::parse($requestTarget);
        if ($parts === false) {
            throw new InvalidArgumentException(
                sprintf('Invalid request target: %s', $requestTarget)
            );
        }

        // "absolute-form"
        if (isset($parts['scheme'])) {
            return $requestTarget;
        }

        // "origin-form"
        $invalid = array_diff_key($parts, array_flip(['path', 'query']));
        if (!$invalid) {
            return $requestTarget;
        }
        throw new InvalidArgumentException(
            sprintf('origin-form of request-target cannot have URI components other than path, query: %s', $requestTarget)
        );
    }

    /**
     * @param PsrUriInterface|Stringable|string $uri
     */
    private function filterUri($uri): Uri
    {
        // `Psr\Http\Message\UriInterface` makes no distinction between empty
        // and undefined URI components, but `/path?` and `/path` are not
        // necessarily equivalent, so URIs are always converted to instances of
        // `Salient\Http\Uri`, which surfaces empty and undefined queries via
        // `Uri::toParts()` as `""` and `null` respectively
        return Uri::from($uri);
    }
}
