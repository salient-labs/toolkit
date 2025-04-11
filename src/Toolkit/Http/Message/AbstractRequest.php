<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Http\Message\RequestInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Http\HttpUtil;
use Salient\Http\Uri;
use Salient\Utility\Regex;
use InvalidArgumentException;
use Stringable;

/**
 * @template TPsr7 of PsrRequestInterface
 *
 * @extends AbstractMessage<TPsr7>
 * @implements RequestInterface<TPsr7>
 */
abstract class AbstractRequest extends AbstractMessage implements RequestInterface
{
    use ImmutableTrait;

    protected string $Method;
    protected ?string $RequestTarget;
    protected Uri $Uri;

    /**
     * @param PsrUriInterface|Stringable|string $uri
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

        if ($this->Headers->getHeaderLine(self::HEADER_HOST) !== '') {
            return;
        }
        $host = $this->getUriHost();
        if ($host === '') {
            return;
        }
        $this->Headers = $this->Headers->set(self::HEADER_HOST, $host);
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

        $query = $this->Uri->getComponents()['query'] ?? null;
        if ($query !== null) {
            return "$target?$query";
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
    public function withRequestTarget(string $requestTarget): PsrRequestInterface
    {
        return $this->with('RequestTarget', $this->filterRequestTarget($requestTarget));
    }

    /**
     * @inheritDoc
     */
    public function withMethod(string $method): PsrRequestInterface
    {
        return $this->with('Method', $this->filterMethod($method));
    }

    /**
     * @inheritDoc
     */
    public function withUri(PsrUriInterface $uri, bool $preserveHost = false): PsrRequestInterface
    {
        if ((string) $uri === (string) $this->Uri) {
            $instance = $this;
        } else {
            $instance = $this->with('Uri', $this->filterUri($uri));
        }

        if (
            $preserveHost
            && $instance->Headers->getHeaderLine(self::HEADER_HOST) !== ''
        ) {
            return $instance;
        }

        $host = $instance->getUriHost();
        if ($host === '') {
            return $instance;
        }
        return $instance->withHeader(self::HEADER_HOST, $host);
    }

    /**
     * @return array{method:string,url:string,httpVersion:string,cookies:array<array{name:string,value:string,path?:string,domain?:string,expires?:string,httpOnly?:bool,secure?:bool}>,headers:array<array{name:string,value:string}>,queryString:array<array{name:string,value:string}>,postData?:array{mimeType:string,params:array{},text:string},headersSize:int,bodySize:int}
     */
    public function jsonSerialize(): array
    {
        $request = parent::jsonSerialize();

        if (
            $request['bodySize'] === -1
            || $request['bodySize'] > 0
            || ([
                self::METHOD_POST => true,
                self::METHOD_PUT => true,
                self::METHOD_PATCH => true,
                self::METHOD_DELETE => true,
            ][$this->Method] ?? false)
        ) {
            $mediaType = $this->Headers->getHeaderValues(self::HEADER_CONTENT_TYPE);
            $mediaType = count($mediaType) === 1 ? $mediaType[0] : '';
            $body = (string) $this->Body;
            $postData = [
                'postData' => [
                    'mimeType' => $mediaType,
                    'params' => HttpUtil::mediaTypeIs($mediaType, self::TYPE_FORM)
                        ? $this->splitQuery($body)
                        : [],
                    'text' => $body,
                ],
            ];
        } else {
            $postData = [];
        }

        return [
            'method' => $this->Method,
            'url' => (string) $this->Uri,
            'httpVersion' => $request['httpVersion'],
            'cookies' => $request['cookies'],
            'headers' => $request['headers'],
            'queryString' => $this->splitQuery($this->Uri->getQuery()),
        ] + $postData + $request;
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
        if (!Regex::match('/^' . Regex::HTTP_TOKEN . '$/D', $method)) {
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
        if (HttpUtil::requestTargetIsAuthorityForm($requestTarget)) {
            return $requestTarget;
        }

        /** @var array<string,string|int>|false */
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
        // `Uri::getComponents()` as `""` and `null` respectively
        return Uri::from($uri);
    }

    /**
     * @return array<array{name:string,value:string}>
     */
    private function splitQuery(string $query): array
    {
        if ($query === '') {
            return [];
        }
        foreach (explode('&', $query) as $param) {
            $param = explode('=', $param, 2);
            $params[] = [
                'name' => $param[0],
                'value' => $param[1] ?? '',
            ];
        }
        return $params;
    }
}
