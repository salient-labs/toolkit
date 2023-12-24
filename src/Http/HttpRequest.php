<?php declare(strict_types=1);

namespace Lkrms\Http;

use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Http\Catalog\HttpHeader;
use Lkrms\Http\Catalog\HttpRequestMethod;
use Lkrms\Http\Contract\HttpHeadersInterface;
use Lkrms\Utility\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Stringable;

/**
 * An outgoing HTTP request
 */
class HttpRequest extends HttpMessage implements RequestInterface
{
    protected string $Method;

    protected ?string $RequestTarget;

    protected Uri $Uri;

    /**
     * @param UriInterface|Stringable|string $uri
     * @param StreamInterface|resource|string|null $body
     * @param HttpHeadersInterface|array<string,string[]|string>|null $headers
     */
    public function __construct(
        $uri,
        string $method = HttpRequestMethod::GET,
        ?string $requestTarget = null,
        $body = null,
        $headers = null,
        string $version = '1.1'
    ) {
        $this->Method = $this->filterMethod($method);
        $this->RequestTarget = $this->filterRequestTarget($requestTarget);
        $this->Uri = $this->filterUri($uri);

        parent::__construct($body, $headers, $version);

        if ($this->Headers->getHeaderLine(HttpHeader::HOST) !== '') {
            return;
        }
        $host = $this->getHost();
        if ($host === '') {
            return;
        }
        $this->Headers = $this->Headers->set(HttpHeader::HOST, $host);
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
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        if ((string) $uri === (string) $this->Uri) {
            $instance = $this;
        } else {
            $instance = $this->with('Uri', $this->filterUri($uri));
        }

        if (
            $preserveHost &&
            $instance->Headers->getHeaderLine(HttpHeader::HOST) !== ''
        ) {
            return $instance;
        }

        $host = $instance->getHost();
        if ($host === '') {
            return $instance;
        }
        return $instance->withHeader(HttpHeader::HOST, $host);
    }

    private function getHost(): string
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
        if (!HttpRequestMethod::hasValue(Str::upper($method))) {
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
        if ((string) $requestTarget === '') {
            return null;
        }

        // "asterisk-form"
        if ($requestTarget === '*') {
            return $requestTarget;
        }

        try {
            $parts = Uri::parse($requestTarget);
        } catch (InvalidArgumentException $ex) {
            throw new InvalidArgumentException(
                sprintf('Invalid request target: %s', $requestTarget),
                $ex
            );
        }

        // "absolute-form"
        if (isset($parts['scheme'])) {
            return $requestTarget;
        }

        // "authority-form"
        if (isset($parts['host'])) {
            $invalid = array_diff_key($parts, array_flip(['host', 'port', 'user', 'pass']));
            if (!$invalid) {
                return $requestTarget;
            }
            throw new InvalidArgumentException(
                sprintf('authority-form of request-target cannot have URI components other than host, port, userinfo: %s', $requestTarget)
            );
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
     * @param UriInterface|Stringable|string $uri
     */
    private function filterUri($uri): Uri
    {
        // `UriInterface` makes no distinction between empty and undefined URI
        // components, but `/path?` and `/path` are not necessarily equivalent,
        // so URIs are always converted to instances of `Lkrms\Http\Uri`, which
        // surfaces empty and undefined queries as `""` and `null` respectively
        return Uri::from($uri)->normalise();
    }
}
