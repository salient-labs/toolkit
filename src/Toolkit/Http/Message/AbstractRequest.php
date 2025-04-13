<?php declare(strict_types=1);

namespace Salient\Http\Message;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Http\Message\RequestInterface;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Http\HttpUtil;
use Salient\Http\Uri;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use Stringable;

/**
 * @internal
 *
 * @template TPsr7 of PsrRequestInterface
 *
 * @extends AbstractMessage<TPsr7>
 * @implements RequestInterface<TPsr7>
 */
abstract class AbstractRequest extends AbstractMessage implements RequestInterface
{
    use ImmutableTrait;

    protected ?string $RequestTarget;
    protected string $Method;
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
        $this->RequestTarget = $this->filterRequestTarget((string) $requestTarget);
        $this->Method = $this->filterMethod($method);
        $this->Uri = $this->filterUri($uri);

        parent::__construct($body, $headers, $version);

        if (
            !$this->hasHostHeader()
            && ($host = $this->getUriHost()) !== ''
        ) {
            $this->Headers = $this->Headers->set(self::HEADER_HOST, $host);
        }
    }

    /**
     * @inheritDoc
     */
    public function getRequestTarget(): string
    {
        if ($this->RequestTarget !== null) {
            return $this->RequestTarget;
        }

        // As per [RFC7230] Section 5.3.1 ("origin-form")
        $query = $this->Uri->getComponents()['query'] ?? null;
        return Str::coalesce($this->Uri->getPath(), '/')
            . ($query === null ? '' : '?' . $query);
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
        $request = (
            (string) $uri === (string) $this->Uri
            || (string) ($uri = $this->filterUri($uri)) === (string) $this->Uri
        )
            ? $this
            : $this->with('Uri', $uri);

        return (
            ($preserveHost && $request->hasHostHeader())
            || ($host = $request->getUriHost()) === ''
        )
            ? $request
            : $request->withHeader(self::HEADER_HOST, $host);
    }

    private function filterRequestTarget(string $requestTarget): ?string
    {
        if ($requestTarget === '') {
            return null;
        }

        // As per [RFC7230] Section 5.3 ("Request Target")
        /** @disregard P1006 */
        if (
            // "asterisk-form"
            $requestTarget === '*'
            // "authority-form"
            || HttpUtil::requestTargetIsAuthorityForm($requestTarget)
            || (($parts = Uri::parse($requestTarget)) !== false && (
                // "absolute-form"
                isset($parts['scheme'])
                // "origin-form"
                || !array_diff_key($parts, ['path' => null, 'query' => null])
            ))
        ) {
            return $requestTarget;
        }

        throw new InvalidArgumentException(
            sprintf('Invalid request target: %s', $requestTarget),
        );
    }

    private function filterMethod(string $method): string
    {
        if (!Regex::match('/^' . Regex::HTTP_TOKEN . '$/D', $method)) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP method: %s', $method),
            );
        }
        return $method;
    }

    /**
     * @param PsrUriInterface|Stringable|string $uri
     */
    private function filterUri($uri): Uri
    {
        // `\Psr\Http\Message\UriInterface` makes no distinction between empty
        // and undefined URI components, but `/path?` and `/path` are not
        // necessarily equivalent, so URIs are converted to `\Salient\Http\Uri`,
        // allowing the distinction to be made via `getComponents()`
        return Uri::from($uri);
    }

    private function hasHostHeader(): bool
    {
        return $this->Headers->getHeaderLine(self::HEADER_HOST) !== '';
    }

    private function getUriHost(): string
    {
        $host = $this->Uri->getHost();
        if ($host !== '') {
            $port = $this->Uri->getPort();
            if ($port !== null) {
                $host .= ':' . $port;
            }
        }
        return $host;
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
            $mediaType = $this->Headers->getLastHeaderValue(self::HEADER_CONTENT_TYPE);
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
