<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Cache\CacheInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Curler\Exception\CurlErrorException as CurlErrorExceptionInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerMiddlewareInterface;
use Salient\Contract\Curler\CurlerPageRequestInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\Exception\InvalidHeaderException as InvalidHeaderExceptionInterface;
use Salient\Contract\Http\Exception\StreamEncapsulationException;
use Salient\Contract\Http\Message\HttpMultipartStreamInterface;
use Salient\Contract\Http\Message\HttpResponseInterface;
use Salient\Contract\Http\AccessTokenInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpRequestHandlerInterface;
use Salient\Contract\Http\UriInterface;
use Salient\Core\Concern\BuildableTrait;
use Salient\Core\Concern\ImmutableTrait;
use Salient\Core\Facade\Cache;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Event;
use Salient\Curler\Event\CurlRequestEvent;
use Salient\Curler\Event\CurlResponseEvent;
use Salient\Curler\Event\ResponseCacheHitEvent;
use Salient\Curler\Exception\CurlErrorException;
use Salient\Curler\Exception\HttpErrorException;
use Salient\Curler\Exception\NetworkException;
use Salient\Curler\Exception\RequestException;
use Salient\Curler\Exception\TooManyRedirectsException;
use Salient\Http\Exception\InvalidHeaderException;
use Salient\Http\HasHttpHeaders;
use Salient\Http\HttpHeaders;
use Salient\Http\HttpRequest;
use Salient\Http\HttpResponse;
use Salient\Http\HttpStream;
use Salient\Http\HttpUtil;
use Salient\Http\Uri;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Str;
use Closure;
use CurlHandle;
use InvalidArgumentException;
use LogicException;
use OutOfRangeException;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * An HTTP client optimised for exchanging data with RESTful API endpoints
 *
 * @api
 *
 * @implements Buildable<CurlerBuilder>
 */
class Curler implements CurlerInterface, Buildable
{
    /** @use BuildableTrait<CurlerBuilder> */
    use BuildableTrait;
    use HasHttpHeaders;
    use ImmutableTrait;

    /**
     * Limit input strings to 2MiB
     *
     * The underlying limit, `CURL_MAX_INPUT_LENGTH`, is 8MB.
     */
    protected const MAX_INPUT_LENGTH = 2 * 1024 ** 2;

    protected const REQUEST_METHOD_HAS_BODY = [
        Curler::METHOD_POST => true,
        Curler::METHOD_PUT => true,
        Curler::METHOD_PATCH => true,
        Curler::METHOD_DELETE => true,
    ];

    protected Uri $Uri;
    protected HttpHeadersInterface $Headers;
    protected ?AccessTokenInterface $AccessToken = null;
    protected string $AccessTokenHeaderName;
    /** @var array<string,true> */
    protected array $SensitiveHeaders;
    protected ?string $MediaType = null;
    protected ?string $UserAgent = null;
    protected bool $ExpectJson = true;
    protected bool $PostJson = true;
    protected ?DateFormatterInterface $DateFormatter = null;
    /** @var int-mask-of<Curler::PRESERVE_*> */
    protected int $FormDataFlags = Curler::PRESERVE_NUMERIC_KEYS | Curler::PRESERVE_STRING_KEYS;
    /** @var int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_OBJECT_AS_ARRAY|\JSON_THROW_ON_ERROR> */
    protected int $JsonDecodeFlags = \JSON_OBJECT_AS_ARRAY;
    /** @var array<array{CurlerMiddlewareInterface|HttpRequestHandlerInterface|Closure(RequestInterface $request, Closure(RequestInterface): HttpResponseInterface $next, CurlerInterface $curler): ResponseInterface,string|null}> */
    protected array $Middleware = [];
    protected ?CurlerPagerInterface $Pager = null;
    protected bool $AlwaysPaginate = false;
    protected ?CacheInterface $Cache = null;
    protected ?string $CookiesCacheKey = null;
    protected bool $CacheResponses = false;
    protected bool $CachePostResponses = false;
    /** @var (callable(RequestInterface $request, CurlerInterface $curler): (string[]|string))|null */
    protected $CacheKeyCallback = null;
    /** @var int<-1,max> */
    protected int $CacheLifetime = 3600;
    protected bool $RefreshCache = false;
    /** @var int<0,max>|null */
    protected ?int $Timeout = null;
    protected bool $FollowRedirects = false;
    /** @var int<-1,max>|null */
    protected ?int $MaxRedirects = null;
    protected bool $RetryAfterTooManyRequests = false;
    /** @var int<0,max> */
    protected int $RetryAfterMaxSeconds = 300;
    protected bool $ThrowHttpErrors = true;

    // --

    protected ?RequestInterface $LastRequest = null;
    protected ?HttpResponseInterface $LastResponse = null;
    private ?Curler $WithoutThrowHttpErrors = null;
    private ?Closure $Closure = null;

    // --

    private static string $DefaultUserAgent;
    /** @var array<string,true> */
    private static array $UnstableHeaders;
    /** @var CurlHandle|resource|null */
    private static $Handle;

    /**
     * @api
     *
     * @param PsrUriInterface|Stringable|string|null $uri Endpoint URI (cannot have query or fragment components)
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $headers Request headers
     * @param string[] $sensitiveHeaders Headers treated as sensitive
     */
    final public function __construct(
        $uri = null,
        $headers = null,
        array $sensitiveHeaders = Curler::HEADERS_SENSITIVE
    ) {
        $this->Uri = $this->filterUri($uri);
        $this->Headers = $this->filterHeaders($headers);
        $this->SensitiveHeaders = array_change_key_case(
            array_fill_keys($sensitiveHeaders, true),
        );
    }

    /**
     * @internal
     *
     * @param PsrUriInterface|Stringable|string|null $uri Endpoint URI (cannot have query or fragment components)
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $headers Request headers
     * @param AccessTokenInterface|null $accessToken Access token applied to request headers
     * @param string $accessTokenHeaderName Name of access token header (default: `"Authorization"`)
     * @param string[] $sensitiveHeaders Headers treated as sensitive (default: {@see Curler::HEADERS_SENSITIVE})
     * @param string|null $mediaType Media type applied to request headers
     * @param string|null $userAgent User agent applied to request headers
     * @param bool $expectJson Explicitly accept JSON-encoded responses and assume responses with no content type contain JSON
     * @param bool $postJson Use JSON to encode POST/PUT/PATCH/DELETE data
     * @param DateFormatterInterface|null $dateFormatter Date formatter used to format and parse the endpoint's date and time values
     * @param int-mask-of<Curler::PRESERVE_*> $formDataFlags Flags used to encode data for query strings and message bodies (default: {@see Curler::PRESERVE_NUMERIC_KEYS} `|` {@see Curler::PRESERVE_STRING_KEYS})
     * @param int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_OBJECT_AS_ARRAY|\JSON_THROW_ON_ERROR> $jsonDecodeFlags Flags used to decode JSON returned by the endpoint (default: {@see \JSON_OBJECT_AS_ARRAY})
     * @param array<array{CurlerMiddlewareInterface|HttpRequestHandlerInterface|Closure(RequestInterface $request, Closure(RequestInterface): HttpResponseInterface $next, CurlerInterface $curler): ResponseInterface,1?:string|null}> $middleware Middleware applied to the request handler stack
     * @param CurlerPagerInterface|null $pager Pagination handler
     * @param bool $alwaysPaginate Use the pager to process requests even if no pagination is required
     * @param CacheInterface|null $cache Cache to use for cookie and response storage instead of the global cache
     * @param bool $handleCookies Enable cookie handling
     * @param string|null $cookiesCacheKey Key to cache cookies under (cookie handling is implicitly enabled if given)
     * @param bool $cacheResponses Cache responses to GET and HEAD requests (HTTP caching headers are ignored; USE RESPONSIBLY)
     * @param bool $cachePostResponses Cache responses to repeatable POST requests (ignored if GET and HEAD request caching is disabled)
     * @param (callable(RequestInterface $request, CurlerInterface $curler): (string[]|string))|null $cacheKeyCallback Override values hashed and combined with request method and URI to create response cache keys (headers not in {@see Curler::HEADERS_UNSTABLE} are used by default)
     * @param int<-1,max> $cacheLifetime Seconds before cached responses expire when caching is enabled (`0` = cache indefinitely; `-1` = do not cache; default: `3600`)
     * @param bool $refreshCache Replace cached responses even if they haven't expired
     * @param int<0,max>|null $timeout Connection timeout in seconds (`null` = use underlying default of `300` seconds; default: `null`)
     * @param bool $followRedirects Follow "Location" headers
     * @param int<-1,max>|null $maxRedirects Limit the number of "Location" headers followed (`-1` = unlimited; `0` = do not follow redirects; `null` = use underlying default of `30`; default: `null`)
     * @param bool $retryAfterTooManyRequests Retry throttled requests when the endpoint returns a "Retry-After" header
     * @param int<0,max> $retryAfterMaxSeconds Limit the delay between request attempts (`0` = unlimited; default: `300`)
     * @param bool $throwHttpErrors Throw exceptions for HTTP errors
     * @return static
     */
    public static function create(
        $uri = null,
        $headers = null,
        ?AccessTokenInterface $accessToken = null,
        string $accessTokenHeaderName = Curler::HEADER_AUTHORIZATION,
        array $sensitiveHeaders = Curler::HEADERS_SENSITIVE,
        ?string $mediaType = null,
        ?string $userAgent = null,
        bool $expectJson = true,
        bool $postJson = true,
        ?DateFormatterInterface $dateFormatter = null,
        int $formDataFlags = Curler::PRESERVE_NUMERIC_KEYS | Curler::PRESERVE_STRING_KEYS,
        int $jsonDecodeFlags = \JSON_OBJECT_AS_ARRAY,
        array $middleware = [],
        ?CurlerPagerInterface $pager = null,
        bool $alwaysPaginate = false,
        ?CacheInterface $cache = null,
        bool $handleCookies = false,
        ?string $cookiesCacheKey = null,
        bool $cacheResponses = false,
        bool $cachePostResponses = false,
        ?callable $cacheKeyCallback = null,
        int $cacheLifetime = 3600,
        bool $refreshCache = false,
        ?int $timeout = null,
        bool $followRedirects = false,
        ?int $maxRedirects = null,
        bool $retryAfterTooManyRequests = false,
        int $retryAfterMaxSeconds = 300,
        bool $throwHttpErrors = true
    ): self {
        $curler = new static($uri, $headers, $sensitiveHeaders);
        $curler->AccessToken = $accessToken;
        if ($accessToken !== null) {
            $curler->AccessTokenHeaderName = $accessTokenHeaderName;
        }
        $curler->MediaType = $mediaType;
        $curler->UserAgent = $userAgent;
        $curler->ExpectJson = $expectJson;
        $curler->PostJson = $postJson;
        $curler->DateFormatter = $dateFormatter;
        $curler->FormDataFlags = $formDataFlags;
        $curler->JsonDecodeFlags = $jsonDecodeFlags;
        foreach ($middleware as $value) {
            $curler->Middleware[] = [$value[0], $value[1] ?? null];
        }
        $curler->Pager = $pager;
        $curler->AlwaysPaginate = $pager && $alwaysPaginate;
        $curler->Cache = $cache;
        $curler->CookiesCacheKey = $handleCookies || $cookiesCacheKey !== null
            ? self::filterCookiesCacheKey($cookiesCacheKey)
            : null;
        $curler->CacheResponses = $cacheResponses;
        $curler->CachePostResponses = $cachePostResponses;
        $curler->CacheKeyCallback = $cacheKeyCallback;
        $curler->CacheLifetime = $cacheLifetime;
        $curler->RefreshCache = $refreshCache;
        $curler->Timeout = $timeout;
        $curler->FollowRedirects = $followRedirects;
        $curler->MaxRedirects = $maxRedirects;
        $curler->RetryAfterTooManyRequests = $retryAfterTooManyRequests;
        $curler->RetryAfterMaxSeconds = $retryAfterMaxSeconds;
        $curler->ThrowHttpErrors = $throwHttpErrors;
        return $curler;
    }

    private function __clone()
    {
        $this->LastRequest = null;
        $this->LastResponse = null;
        $this->WithoutThrowHttpErrors = null;
        $this->Closure = null;
    }

    /**
     * @inheritDoc
     */
    public function getUri(): UriInterface
    {
        return $this->Uri;
    }

    /**
     * @inheritDoc
     */
    public function getLastRequest(): ?RequestInterface
    {
        return $this->LastRequest;
    }

    /**
     * @inheritDoc
     */
    public function getLastResponse(): ?HttpResponseInterface
    {
        return $this->LastResponse;
    }

    /**
     * @inheritDoc
     */
    public function lastResponseIsJson(): bool
    {
        if ($this->LastResponse === null) {
            throw new OutOfRangeException('No response to check');
        }
        return $this->responseIsJson($this->LastResponse);
    }

    private function responseIsJson(HttpResponseInterface $response): bool
    {
        $headers = $response->getHttpHeaders();
        if (!$headers->hasHeader(self::HEADER_CONTENT_TYPE)) {
            return $this->ExpectJson;
        }
        try {
            $contentType = $headers->getOnlyHeaderValue(self::HEADER_CONTENT_TYPE);
        } catch (InvalidHeaderExceptionInterface $ex) {
            $this->debug($ex->getMessage());
            return false;
        }
        return HttpUtil::mediaTypeIs($contentType, self::TYPE_JSON);
    }

    // --

    /**
     * @inheritDoc
     */
    public function head(?array $query = null): HttpHeadersInterface
    {
        return $this->process(self::METHOD_HEAD, $query);
    }

    /**
     * @inheritDoc
     */
    public function get(?array $query = null)
    {
        return $this->process(self::METHOD_GET, $query);
    }

    /**
     * @inheritDoc
     */
    public function post($data = null, ?array $query = null)
    {
        return $this->process(self::METHOD_POST, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function put($data = null, ?array $query = null)
    {
        return $this->process(self::METHOD_PUT, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function patch($data = null, ?array $query = null)
    {
        return $this->process(self::METHOD_PATCH, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function delete($data = null, ?array $query = null)
    {
        return $this->process(self::METHOD_DELETE, $query, $data);
    }

    /**
     * @param mixed[]|null $query
     * @param mixed[]|object|false|null $data
     * @return ($method is self::METHOD_HEAD ? HttpHeadersInterface : mixed)
     */
    private function process(string $method, ?array $query, $data = false)
    {
        $request = $this->createRequest($method, $query, $data);
        $pager = $this->AlwaysPaginate ? $this->Pager : null;
        if ($pager) {
            $request = $pager->getFirstRequest($request, $this, $query);
            if ($request instanceof CurlerPageRequestInterface) {
                $query = $request->getQuery() ?? $query;
                $request = $request->getRequest();
            }
        }
        $response = $this->doSendRequest($request);
        if ($method === self::METHOD_HEAD) {
            return $response->getHttpHeaders();
        }
        $result = $this->getResponseBody($response);
        if ($pager) {
            $page = $pager->getPage($result, $request, $response, $this, null, $query);
            return Arr::unwrap($page->getEntities(), 1);
        }
        return $result;
    }

    // --

    /**
     * @inheritDoc
     */
    public function getP(?array $query = null): iterable
    {
        return $this->paginate(self::METHOD_GET, $query);
    }

    /**
     * @inheritDoc
     */
    public function postP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(self::METHOD_POST, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function putP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(self::METHOD_PUT, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function patchP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(self::METHOD_PATCH, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function deleteP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(self::METHOD_DELETE, $query, $data);
    }

    /**
     * @param mixed[]|null $query
     * @param mixed[]|object|false|null $data
     * @return iterable<mixed>
     */
    private function paginate(string $method, ?array $query, $data = false): iterable
    {
        if ($this->Pager === null) {
            throw new LogicException('No pager');
        }
        $pager = $this->Pager;
        $request = $this->createRequest($method, $query, $data);
        $request = $pager->getFirstRequest($request, $this, $query);
        $prev = null;
        do {
            if ($request instanceof CurlerPageRequestInterface) {
                $query = $request->getQuery() ?? $query;
                $request = $request->getRequest();
            }
            $response = $this->doSendRequest($request);
            $result = $this->getResponseBody($response);
            $page = $pager->getPage($result, $request, $response, $this, $prev, $query);
            // Use `yield` instead of `yield from` so entities get unique keys
            foreach ($page->getEntities() as $entity) {
                yield $entity;
            }
            if (!$page->hasNextRequest()) {
                return;
            }
            $request = $page->getNextRequest();
            $prev = $page;
        } while (true);
    }

    // --

    /**
     * @inheritDoc
     */
    public function postR(string $data, string $mediaType, ?array $query = null)
    {
        return $this->processRaw(self::METHOD_POST, $data, $mediaType, $query);
    }

    /**
     * @inheritDoc
     */
    public function putR(string $data, string $mediaType, ?array $query = null)
    {
        return $this->processRaw(self::METHOD_PUT, $data, $mediaType, $query);
    }

    /**
     * @inheritDoc
     */
    public function patchR(string $data, string $mediaType, ?array $query = null)
    {
        return $this->processRaw(self::METHOD_PATCH, $data, $mediaType, $query);
    }

    /**
     * @inheritDoc
     */
    public function deleteR(string $data, string $mediaType, ?array $query = null)
    {
        return $this->processRaw(self::METHOD_DELETE, $data, $mediaType, $query);
    }

    /**
     * @param mixed[]|null $query
     * @return mixed
     */
    private function processRaw(string $method, string $data, string $mediaType, ?array $query)
    {
        $request = $this->createRequest($method, $query, $data);
        $request = $request->withHeader(self::HEADER_CONTENT_TYPE, $mediaType);
        /** @disregard P1006 */
        $response = $this->doSendRequest($request);
        return $this->getResponseBody($response);
    }

    // --

    /**
     * @inheritDoc
     */
    public function flushCookies()
    {
        if ($this->CookiesCacheKey !== null) {
            $this->getCacheInstance()->delete($this->CookiesCacheKey);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function replaceQuery($value, array $query)
    {
        return HttpUtil::replaceQuery(
            $value,
            $query,
            $this->FormDataFlags,
            $this->DateFormatter,
        );
    }

    // --

    /**
     * @inheritDoc
     */
    public function getHttpHeaders(): HttpHeadersInterface
    {
        $headers = $this->Headers;
        if ($this->AccessToken !== null) {
            $headers = $headers->authorize($this->AccessToken, $this->AccessTokenHeaderName);
        }
        if ($this->MediaType !== null) {
            $headers = $headers->set(self::HEADER_CONTENT_TYPE, $this->MediaType);
        }
        if ($this->ExpectJson) {
            $headers = $headers->set(self::HEADER_ACCEPT, self::TYPE_JSON);
        }
        if ($this->UserAgent !== '' && (
            $this->UserAgent !== null
            || !$headers->hasHeader(self::HEADER_USER_AGENT)
        )) {
            $headers = $headers->set(
                self::HEADER_USER_AGENT,
                $this->UserAgent ?? $this->getDefaultUserAgent(),
            );
        }
        return $headers;
    }

    /**
     * @inheritDoc
     */
    public function getPublicHttpHeaders(): HttpHeadersInterface
    {
        $sensitive = $this->SensitiveHeaders;
        if ($this->AccessToken !== null) {
            $sensitive[Str::lower($this->AccessTokenHeaderName)] = true;
        }
        return $this->getHttpHeaders()->exceptIn($sensitive);
    }

    /**
     * @inheritDoc
     */
    public function hasAccessToken(): bool
    {
        return $this->AccessToken !== null;
    }

    /**
     * @inheritDoc
     */
    public function isSensitiveHeader(string $name): bool
    {
        return isset($this->SensitiveHeaders[Str::lower($name)]);
    }

    /**
     * @inheritDoc
     */
    public function getMediaType(): ?string
    {
        return $this->MediaType;
    }

    /**
     * @inheritDoc
     */
    public function hasUserAgent(): bool
    {
        return $this->UserAgent !== null;
    }

    /**
     * @inheritDoc
     */
    public function getUserAgent(): string
    {
        return $this->UserAgent ?? $this->getDefaultUserAgent();
    }

    /**
     * @inheritDoc
     */
    public function expectsJson(): bool
    {
        return $this->ExpectJson;
    }

    /**
     * @inheritDoc
     */
    public function postsJson(): bool
    {
        return $this->PostJson;
    }

    /**
     * @inheritDoc
     */
    public function getDateFormatter(): ?DateFormatterInterface
    {
        return $this->DateFormatter;
    }

    /**
     * @inheritDoc
     */
    public function getFormDataFlags(): int
    {
        return $this->FormDataFlags;
    }

    /**
     * @inheritDoc
     */
    public function getPager(): ?CurlerPagerInterface
    {
        return $this->Pager;
    }

    /**
     * @inheritDoc
     */
    public function alwaysPaginates(): bool
    {
        return $this->AlwaysPaginate;
    }

    /**
     * @inheritDoc
     */
    public function getCache(): ?CacheInterface
    {
        return $this->Cache;
    }

    /**
     * @inheritDoc
     */
    public function hasCookies(): bool
    {
        return $this->CookiesCacheKey !== null;
    }

    /**
     * @inheritDoc
     */
    public function hasResponseCache(): bool
    {
        return $this->CacheResponses;
    }

    /**
     * @inheritDoc
     */
    public function hasPostResponseCache(): bool
    {
        return $this->CachePostResponses;
    }

    /**
     * @inheritDoc
     */
    public function getCacheLifetime(): int
    {
        return $this->CacheLifetime;
    }

    /**
     * @inheritDoc
     */
    public function refreshesCache(): bool
    {
        return $this->RefreshCache;
    }

    /**
     * @inheritDoc
     */
    public function getTimeout(): ?int
    {
        return $this->Timeout;
    }

    /**
     * @inheritDoc
     */
    public function followsRedirects(): bool
    {
        return $this->FollowRedirects;
    }

    /**
     * @inheritDoc
     */
    public function getMaxRedirects(): ?int
    {
        return $this->MaxRedirects;
    }

    /**
     * @inheritDoc
     */
    public function getRetryAfterTooManyRequests(): bool
    {
        return $this->RetryAfterTooManyRequests;
    }

    /**
     * @inheritDoc
     */
    public function getRetryAfterMaxSeconds(): int
    {
        return $this->RetryAfterMaxSeconds;
    }

    /**
     * @inheritDoc
     */
    public function throwsHttpErrors(): bool
    {
        return $this->ThrowHttpErrors;
    }

    // --

    /**
     * @inheritDoc
     */
    public function withUri($uri)
    {
        return (string) $uri === (string) $this->Uri
            ? $this
            : $this->with('Uri', $this->filterUri($uri));
    }

    /**
     * @inheritDoc
     */
    public function withRequest(RequestInterface $request)
    {
        $curler = $this->withUri($request->getUri());

        $headers = HttpHeaders::from($request);
        $_headers = $this->getHttpHeaders();
        if (Arr::same($headers->all(), $_headers->all())) {
            return $curler;
        }

        if ($this->AccessToken !== null) {
            $header = $headers->getHeader($this->AccessTokenHeaderName);
            $_header = $_headers->getHeader($this->AccessTokenHeaderName);
            if ($header !== $_header) {
                $curler = $curler->withAccessToken(null);
            }
        }

        $mediaType = Arr::last($headers->getHeaderValues(self::HEADER_CONTENT_TYPE));
        $userAgent = Arr::last($headers->getHeaderValues(self::HEADER_USER_AGENT));
        $expectJson = Arr::lower($headers->getHeaderValues(self::HEADER_ACCEPT)) === [self::TYPE_JSON];
        if ($userAgent !== null && $userAgent === $this->getDefaultUserAgent()) {
            $userAgent = null;
        }
        return $curler
            ->withMediaType($mediaType)
            ->withUserAgent($userAgent)
            ->withExpectJson($expectJson)
            ->with('Headers', $headers);
    }

    /**
     * @inheritDoc
     */
    public function withAccessToken(
        ?AccessTokenInterface $token,
        string $headerName = Curler::HEADER_AUTHORIZATION
    ) {
        return $token === null
            ? $this
                ->with('AccessToken', null)
                ->without('AccessTokenHeaderName')
            : $this
                ->with('AccessToken', $token)
                ->with('AccessTokenHeaderName', $headerName);
    }

    /**
     * @inheritDoc
     */
    public function withSensitiveHeader(string $name)
    {
        return $this->with(
            'SensitiveHeaders',
            Arr::set($this->SensitiveHeaders, Str::lower($name), true)
        );
    }

    /**
     * @inheritDoc
     */
    public function withoutSensitiveHeader(string $name)
    {
        return $this->with(
            'SensitiveHeaders',
            Arr::unset($this->SensitiveHeaders, Str::lower($name))
        );
    }

    /**
     * @inheritDoc
     */
    public function withMediaType(?string $type)
    {
        return $this->with('MediaType', $type);
    }

    /**
     * @inheritDoc
     */
    public function withUserAgent(?string $userAgent)
    {
        return $this->with('UserAgent', $userAgent);
    }

    /**
     * @inheritDoc
     */
    public function withExpectJson(bool $expectJson = true)
    {
        return $this->with('ExpectJson', $expectJson);
    }

    /**
     * @inheritDoc
     */
    public function withPostJson(bool $postJson = true)
    {
        return $this->with('PostJson', $postJson);
    }

    /**
     * @inheritDoc
     */
    public function withDateFormatter(?DateFormatterInterface $formatter)
    {
        return $this->with('DateFormatter', $formatter);
    }

    /**
     * @inheritDoc
     */
    public function withFormDataFlags(int $flags)
    {
        return $this->with('FormDataFlags', $flags);
    }

    /**
     * @inheritDoc
     */
    public function withJsonDecodeFlags(int $flags)
    {
        return $this->with('JsonDecodeFlags', $flags);
    }

    /**
     * @inheritDoc
     */
    public function withMiddleware($middleware, ?string $name = null)
    {
        return $this->with(
            'Middleware',
            Arr::push($this->Middleware, [$middleware, $name])
        );
    }

    /**
     * @inheritDoc
     */
    public function withoutMiddleware($middleware)
    {
        $index = is_string($middleware) ? 1 : 0;
        $values = $this->Middleware;
        foreach ($values as $key => $value) {
            if ($middleware === $value[$index]) {
                unset($values[$key]);
            }
        }
        return $this->with('Middleware', $values);
    }

    /**
     * @inheritDoc
     */
    public function withPager(?CurlerPagerInterface $pager, bool $alwaysPaginate = false)
    {
        return $this
            ->with('Pager', $pager)
            ->with('AlwaysPaginate', $pager && $alwaysPaginate);
    }

    /**
     * @inheritDoc
     */
    public function withCache(?CacheInterface $cache = null)
    {
        return $this->with('Cache', $cache);
    }

    /**
     * @inheritDoc
     */
    public function withCookies(?string $cacheKey = null)
    {
        return $this->with('CookiesCacheKey', $this->filterCookiesCacheKey($cacheKey));
    }

    /**
     * @inheritDoc
     */
    public function withoutCookies()
    {
        return $this->with('CookiesCacheKey', null);
    }

    /**
     * @inheritDoc
     */
    public function withResponseCache(bool $cacheResponses = true)
    {
        return $this->with('CacheResponses', $cacheResponses);
    }

    /**
     * @inheritDoc
     */
    public function withPostResponseCache(bool $cachePostResponses = true)
    {
        return $this->with('CachePostResponses', $cachePostResponses);
    }

    /**
     * @inheritDoc
     */
    public function withCacheKeyCallback(?callable $callback)
    {
        return $this->with('CacheKeyCallback', $callback);
    }

    /**
     * @inheritDoc
     */
    public function withCacheLifetime(int $seconds)
    {
        return $this->with('CacheLifetime', $seconds);
    }

    /**
     * @inheritDoc
     */
    public function withRefreshCache(bool $refresh = true)
    {
        return $this->with('RefreshCache', $refresh);
    }

    /**
     * @inheritDoc
     */
    public function withTimeout(?int $seconds)
    {
        return $this->with('Timeout', $seconds);
    }

    /**
     * @inheritDoc
     */
    public function withFollowRedirects(bool $follow = true)
    {
        return $this->with('FollowRedirects', $follow);
    }

    /**
     * @inheritDoc
     */
    public function withMaxRedirects(?int $redirects)
    {
        return $this->with('MaxRedirects', $redirects);
    }

    /**
     * @inheritDoc
     */
    public function withRetryAfterTooManyRequests(bool $retry = true)
    {
        return $this->with('RetryAfterTooManyRequests', $retry);
    }

    /**
     * @inheritDoc
     */
    public function withRetryAfterMaxSeconds(int $seconds)
    {
        return $this->with('RetryAfterMaxSeconds', $seconds);
    }

    /**
     * @inheritDoc
     */
    public function withThrowHttpErrors(bool $throw = true)
    {
        return $this->with('ThrowHttpErrors', $throw);
    }

    // --

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): HttpResponseInterface
    {
        // PSR-18: "A Client MUST NOT treat a well-formed HTTP request or HTTP
        // response as an error condition. For example, response status codes in
        // the 400 and 500 range MUST NOT cause an exception and MUST be
        // returned to the Calling Library as normal."
        $curler = $this->WithoutThrowHttpErrors ??= $this->withThrowHttpErrors(false);
        try {
            try {
                return $curler->doSendRequest($request);
            } finally {
                $this->LastRequest = $curler->LastRequest;
                $this->LastResponse = $curler->LastResponse;
            }
        } catch (ClientExceptionInterface $ex) {
            throw $ex;
        } catch (CurlErrorExceptionInterface $ex) {
            throw $ex->isNetworkError()
                ? new NetworkException($ex->getMessage(), $this->LastRequest ?? $request, [], $ex)
                : new RequestException($ex->getMessage(), $this->LastRequest ?? $request, [], $ex);
        } catch (Throwable $ex) {
            throw new RequestException($ex->getMessage(), $this->LastRequest ?? $request, [], $ex);
        }
    }

    /**
     * @phpstan-assert !null $this->LastRequest
     * @phpstan-assert !null $this->LastResponse
     */
    private function doSendRequest(RequestInterface $request): HttpResponseInterface
    {
        $this->LastRequest = null;
        $this->LastResponse = null;
        return $this->Middleware
            ? ($this->Closure ??= $this->getClosure())($request)
            : $this->getResponse($request);
    }

    /**
     * @return Closure(RequestInterface): HttpResponseInterface
     */
    private function getClosure(): Closure
    {
        $closure = fn(RequestInterface $request): HttpResponseInterface =>
            $this->getResponse($request);
        foreach (array_reverse($this->Middleware) as [$middleware]) {
            $closure = $middleware instanceof CurlerMiddlewareInterface
                ? fn(RequestInterface $request): HttpResponseInterface =>
                    $this->handleHttpResponse($middleware($request, $closure, $this), $request)
                : ($middleware instanceof HttpRequestHandlerInterface
                    ? fn(RequestInterface $request): HttpResponseInterface =>
                        $this->handleResponse($middleware($request, $closure), $request)
                    : fn(RequestInterface $request): HttpResponseInterface =>
                        $this->handleResponse($middleware($request, $closure, $this), $request));
        }
        return $closure;
    }

    private function getResponse(RequestInterface $request): HttpResponseInterface
    {
        $uri = $request->getUri()->withFragment('');
        $request = $request->withUri($uri);

        $version = (int) ((float) $request->getProtocolVersion() * 10);
        $opt[\CURLOPT_HTTP_VERSION] = [
            10 => \CURL_HTTP_VERSION_1_0,
            11 => \CURL_HTTP_VERSION_1_1,
            20 => \CURL_HTTP_VERSION_2_0,
        ][$version] ?? \CURL_HTTP_VERSION_NONE;

        $method = $request->getMethod();
        $body = $request->getBody();
        $opt[\CURLOPT_CUSTOMREQUEST] = $method;
        if ($method === self::METHOD_HEAD) {
            $opt[\CURLOPT_NOBODY] = true;
            $size = 0;
        } else {
            $size = $body->getSize();
        }

        if ($size === null || $size > 0) {
            $size ??= HttpHeaders::from($request)->getContentLength();
            if ($size !== null && $size <= static::MAX_INPUT_LENGTH) {
                $body = (string) $body;
                $opt[\CURLOPT_POSTFIELDS] = $body;
                $request = $request
                    ->withoutHeader(self::HEADER_CONTENT_LENGTH)
                    ->withoutHeader(self::HEADER_TRANSFER_ENCODING);
            } else {
                $opt[\CURLOPT_UPLOAD] = true;
                if ($size !== null) {
                    $opt[\CURLOPT_INFILESIZE] = $size;
                    $request = $request
                        ->withoutHeader(self::HEADER_CONTENT_LENGTH);
                }
                $opt[\CURLOPT_READFUNCTION] =
                    static function ($handle, $infile, int $length) use ($body): string {
                        return $body->read($length);
                    };
                if ($body->isSeekable()) {
                    $body->rewind();
                }
            }
        } elseif (self::REQUEST_METHOD_HAS_BODY[$method] ?? false) {
            // [RFC7230], Section 3.3.2: "A user agent SHOULD send a
            // Content-Length in a request message when no Transfer-Encoding is
            // sent and the request method defines a meaning for an enclosed
            // payload body. For example, a Content-Length header field is
            // normally sent in a POST request even when the value is 0
            // (indicating an empty payload body)."
            $request = $request->withHeader(self::HEADER_CONTENT_LENGTH, '0');
        }

        if ($this->Timeout !== null) {
            $opt[\CURLOPT_CONNECTTIMEOUT] = $this->Timeout;
        }

        if (!$request->hasHeader(self::HEADER_ACCEPT_ENCODING)) {
            // Enable all supported encodings (e.g. gzip, deflate) and set
            // `Accept-Encoding` accordingly
            $opt[\CURLOPT_ENCODING] = '';
        }

        /** @var string|null */
        $statusLine = null;
        /** @var HttpHeaders|null */
        $headersIn = null;
        $opt[\CURLOPT_HEADERFUNCTION] =
            static function ($handle, string $header) use (&$statusLine, &$headersIn): int {
                if (substr($header, 0, 5) === 'HTTP/') {
                    $statusLine = rtrim($header, "\r\n");
                    $headersIn = new HttpHeaders();
                    return strlen($header);
                }
                if ($headersIn === null) {
                    throw new InvalidHeaderException('No status line in HTTP response');
                }
                $headersIn = $headersIn->addLine($header);
                return strlen($header);
            };

        /** @var HttpStream|null */
        $bodyIn = null;
        $opt[\CURLOPT_WRITEFUNCTION] =
            static function ($handle, string $data) use (&$bodyIn): int {
                /** @var HttpStream $bodyIn */
                return $bodyIn->write($data);
            };

        if (self::$Handle === null) {
            $handle = curl_init((string) $uri);
            if ($handle === false) {
                throw new RuntimeException('curl_init() failed');
            }
            self::$Handle = $handle;
            $resetHandle = false;
        } else {
            $opt[\CURLOPT_URL] = (string) $uri;
            $resetHandle = true;
        }

        $request = $this->normaliseRequest($request, $uri);
        $headers = HttpHeaders::from($request);

        $cacheKey = null;
        $transfer = 0;
        $redirects = $this->FollowRedirects && $this->MaxRedirects !== 0
            ? $this->MaxRedirects ?? 30
            : false;
        $retrying = false;
        do {
            if ($cacheKey === null) {
                if (
                    $this->CacheResponses
                    && ($size === 0 || is_string($body))
                    && ([
                        self::METHOD_GET => true,
                        self::METHOD_HEAD => true,
                        self::METHOD_POST => $this->CachePostResponses,
                    ][$method] ?? false)
                ) {
                    $cacheKey = $this->CacheKeyCallback !== null
                        ? (array) ($this->CacheKeyCallback)($request, $this)
                        : $headers->exceptIn($this->getUnstableHeaders())->getLines('%s:%s');

                    if ($size !== 0 || $method === self::METHOD_POST) {
                        $cacheKey[] = $size === 0 ? '' : $body;
                    }

                    $cacheUri = $uri->getPath() === ''
                        ? $uri->withPath('/')
                        : $uri;
                    $cacheKey = implode(':', [
                        self::class,
                        'response',
                        $method,
                        rawurlencode((string) $cacheUri),
                        Get::hash(implode("\0", $cacheKey)),
                    ]);
                } else {
                    $cacheKey = false;
                }
            }

            if (
                $cacheKey !== false
                && !$this->RefreshCache
                && ($last = $this->getCacheInstance()->getArray($cacheKey)) !== null
            ) {
                /** @var array{code:int,body:string,headers:array<array{name:string,value:string}>|HttpHeaders,reason:string|null,version:string}|array{int,string,HttpHeaders,string} $last */
                $code = $last['code'] ?? $last[0] ?? 200;
                $bodyIn = HttpStream::fromString($last['body'] ?? $last[3] ?? '');
                $lastHeaders = $last['headers'] ?? $last[2] ?? null;
                if (is_array($lastHeaders)) {
                    $lastHeaders = HttpUtil::getNameValueGenerator($lastHeaders);
                }
                $headersIn = HttpHeaders::from($lastHeaders ?? []);
                $reason = $last['reason'] ?? $last[1] ?? null;
                $version = $last['version'] ?? '1.1';
                $response = new HttpResponse($code, $bodyIn, $headersIn, $reason, $version);
                Event::dispatch(new ResponseCacheHitEvent($this, $request, $response));
            } else {
                if ($transfer) {
                    if ($size !== 0 && $body instanceof StreamInterface) {
                        if (!$body->isSeekable()) {
                            throw new RequestException(
                                'Request cannot be sent again (body not seekable)',
                                $request,
                            );
                        }
                        $body->rewind();
                    }
                    $statusLine = null;
                    $headersIn = null;
                }
                $bodyIn = new HttpStream(File::open('php://temp', 'r+'));

                if ($resetHandle || !$transfer) {
                    if ($resetHandle) {
                        curl_reset(self::$Handle);
                        $resetHandle = false;
                    }
                    $opt[\CURLOPT_HTTPHEADER] = $headers->getLines('%s: %s', '%s;');
                    curl_setopt_array(self::$Handle, $opt);

                    if ($this->CookiesCacheKey !== null) {
                        // "If the name is an empty string, no cookies are loaded,
                        // but cookie handling is still enabled"
                        curl_setopt(self::$Handle, \CURLOPT_COOKIEFILE, '');
                        /** @var non-empty-string[] */
                        $cookies = $this->getCacheInstance()->getArray($this->CookiesCacheKey);
                        if ($cookies) {
                            foreach ($cookies as $cookie) {
                                curl_setopt(self::$Handle, \CURLOPT_COOKIELIST, $cookie);
                            }
                        }
                    }
                }

                $transfer++;

                Event::dispatch(new CurlRequestEvent($this, self::$Handle, $request));
                $result = curl_exec(self::$Handle);
                if ($result === false) {
                    throw new CurlErrorException(curl_errno(self::$Handle), $request, $this->getCurlInfo());
                }

                if (
                    $statusLine === null
                    || count($split = explode(' ', $statusLine, 3)) < 2
                    || ($version = explode('/', $split[0])[1] ?? null) === null
                ) {
                    // @codeCoverageIgnoreStart
                    throw new InvalidHeaderException(sprintf(
                        'HTTP status line invalid or not in response: %s',
                        rtrim((string) $statusLine, "\r\n"),
                    ));
                    // @codeCoverageIgnoreEnd
                }

                /** @var HttpHeaders $headersIn */
                $code = (int) $split[1];
                $reason = $split[2] ?? null;
                $response = new HttpResponse($code, $bodyIn, $headersIn, $reason, $version);
                Event::dispatch(new CurlResponseEvent($this, self::$Handle, $request, $response));

                if ($this->CookiesCacheKey !== null) {
                    $this->getCacheInstance()->set(
                        $this->CookiesCacheKey,
                        curl_getinfo(self::$Handle, \CURLINFO_COOKIELIST)
                    );
                }

                if ($cacheKey !== false && $this->CacheLifetime >= 0 && $code < 400) {
                    $ttl = $this->CacheLifetime === 0
                        ? null
                        : $this->CacheLifetime;
                    $this->getCacheInstance()->set($cacheKey, [
                        'code' => $code,
                        'body' => (string) $bodyIn,
                        'headers' => $headersIn->jsonSerialize(),
                        'reason' => $reason,
                        'version' => $version,
                    ], $ttl);
                }
            }

            if (
                $redirects !== false
                && $code >= 300
                && $code < 400
                && count($location = $headersIn->getHeader(self::HEADER_LOCATION)) === 1
                && ($location = $location[0]) !== ''
            ) {
                if (!$redirects) {
                    throw new TooManyRedirectsException(sprintf(
                        'Redirect limit exceeded: %d',
                        $this->MaxRedirects,
                    ), $request, $response);
                }
                $uri = Uri::from($uri)->follow($location)->withFragment('');
                $request = $request->withUri($uri);
                // Match cURL's behaviour
                if (($code === 301 || $code === 302 || $code === 303) && (
                    $size !== 0
                    || (self::REQUEST_METHOD_HAS_BODY[$method] ?? false)
                )) {
                    $method = self::METHOD_GET;
                    $body = HttpStream::fromString('');
                    $request = $request
                        ->withMethod($method)
                        ->withBody($body)
                        ->withoutHeader(self::HEADER_CONTENT_LENGTH)
                        ->withoutHeader(self::HEADER_TRANSFER_ENCODING);
                    $size = 0;
                    $opt[\CURLOPT_CUSTOMREQUEST] = $method;
                    $opt[\CURLOPT_URL] = (string) $uri;
                    unset(
                        $opt[\CURLOPT_POSTFIELDS],
                        $opt[\CURLOPT_UPLOAD],
                        $opt[\CURLOPT_INFILESIZE],
                        $opt[\CURLOPT_READFUNCTION],
                    );
                    $resetHandle = true;
                } else {
                    curl_setopt(
                        self::$Handle,
                        \CURLOPT_URL,
                        // @phpstan-ignore argument.type
                        $opt[\CURLOPT_URL] = (string) $uri,
                    );
                }
                $request = $this->normaliseRequest($request, $uri);
                $headers = HttpHeaders::from($request);
                $cacheKey = null;
                $redirects--;
                $retrying = false;
                continue;
            }

            if (
                !$this->RetryAfterTooManyRequests
                || $retrying
                || $code !== 429
                || ($after = $headersIn->getRetryAfter()) === null
                || ($this->RetryAfterMaxSeconds !== 0 && $after > $this->RetryAfterMaxSeconds)
            ) {
                break;
            }

            $after = max(1, $after);
            $this->debug(Inflect::format($after, 'Sleeping for {{#}} {{#:second}}'));
            sleep($after);
            $retrying = true;
        } while (true);

        $this->LastRequest = $request;
        $this->LastResponse = $response;

        if ($this->ThrowHttpErrors && $code >= 400) {
            throw new HttpErrorException($request, $response, $this->getCurlInfo());
        }

        return $response;
    }

    private function normaliseRequest(
        RequestInterface $request,
        PsrUriInterface $uri
    ): RequestInterface {
        // Remove `Host` if its value is redundant
        if (($host = $request->getHeaderLine(self::HEADER_HOST)) !== '') {
            try {
                $host = new Uri("//$host");
            } catch (InvalidArgumentException $ex) {
                throw new InvalidHeaderException(sprintf(
                    'Invalid value for HTTP request header %s: %s',
                    self::HEADER_HOST,
                    $host,
                ), $ex);
            }
            $host = $host->withScheme($uri->getScheme())->getAuthority();
            if ($host === $uri->withUserInfo('')->getAuthority()) {
                $request = $request->withoutHeader(self::HEADER_HOST);
            }
        }
        return $request;
    }

    // --

    /**
     * @param mixed[]|null $query
     * @param mixed[]|object|string|false|null $data
     */
    private function createRequest(string $method, ?array $query, $data): HttpRequest
    {
        $uri = $this->Uri;
        if ($query) {
            $uri = $this->replaceQuery($uri, $query);
        }
        $headers = $this->getHttpHeaders();
        $request = new HttpRequest($method, $uri, null, $headers);
        return $data !== false
            ? $this->applyData($request, $data)
            : $request;
    }

    /**
     * @param mixed[]|object|string|null $data
     */
    private function applyData(HttpRequest $request, $data): HttpRequest
    {
        if ($data === null) {
            if ($this->PostJson) {
                $this->debug(sprintf(
                    'JSON message bodies cannot be empty; falling back to %s',
                    self::TYPE_FORM,
                ));
            }
            return $request->withHeader(self::HEADER_CONTENT_TYPE, self::TYPE_FORM);
        }
        if (is_string($data)) {
            return $request->withBody(HttpStream::fromString($data));
        }
        if ($this->PostJson) {
            try {
                $body = HttpStream::fromData($data, $this->FormDataFlags, $this->DateFormatter, true);
                $mediaType = self::TYPE_JSON;
            } catch (StreamEncapsulationException $ex) {
                $this->debug(sprintf(
                    '%s; falling back to %s',
                    $ex->getMessage(),
                    self::TYPE_FORM_MULTIPART,
                ));
            }
        }
        $body ??= HttpStream::fromData($data, $this->FormDataFlags, $this->DateFormatter);
        $mediaType ??= $body instanceof HttpMultipartStreamInterface
            ? self::TYPE_FORM_MULTIPART
            : self::TYPE_FORM;
        return $request
            ->withHeader(self::HEADER_CONTENT_TYPE, $mediaType)
            ->withBody($body);
    }

    /**
     * @return mixed
     */
    private function getResponseBody(HttpResponseInterface $response)
    {
        $body = (string) $response->getBody();
        return $this->responseIsJson($response)
            ? ($body === ''
                ? null
                : Json::parse($body, $this->JsonDecodeFlags))
            : $body;
    }

    private function handleResponse(
        ResponseInterface $response,
        RequestInterface $request
    ): HttpResponseInterface {
        return $this->handleHttpResponse(
            $response instanceof HttpResponseInterface
                ? $response
                : HttpResponse::fromPsr7($response),
            $request,
        );
    }

    private function handleHttpResponse(
        HttpResponseInterface $response,
        RequestInterface $request
    ): HttpResponseInterface {
        $this->LastRequest = $request;
        $this->LastResponse = $response;
        return $response;
    }

    /**
     * @param PsrUriInterface|Stringable|string|null $uri
     */
    private function filterUri($uri): Uri
    {
        if ($uri === null) {
            return new Uri();
        }
        $uri = Uri::from($uri);
        $invalid = array_intersect(['query', 'fragment'], array_keys($uri->toParts()));
        if ($invalid) {
            throw new InvalidArgumentException(Inflect::format(
                $invalid,
                'URI cannot have %s {{#:component}}',
                implode(' or ', $invalid),
            ));
        }
        return $uri;
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

    private static function filterCookiesCacheKey(?string $cacheKey): string
    {
        return Arr::implode(':', [self::class, 'cookies', $cacheKey], '');
    }

    private function getCacheInstance(): CacheInterface
    {
        return $this->Cache ?? Cache::getInstance();
    }

    private function getDefaultUserAgent(): string
    {
        return self::$DefaultUserAgent ??= HttpUtil::getProduct();
    }

    /**
     * @return array<string,true>
     */
    private function getUnstableHeaders(): array
    {
        return self::$UnstableHeaders ??= array_fill_keys(self::HEADERS_UNSTABLE, true);
    }

    /**
     * @return array<string,mixed>
     */
    private function getCurlInfo(): array
    {
        if (
            self::$Handle === null
            || ($info = curl_getinfo(self::$Handle)) === false
        ) {
            return [];
        }
        foreach ($info as &$value) {
            if (is_string($value)) {
                $value = trim($value);
            }
        }
        return $info;
    }

    private function debug(string $msg1): void
    {
        if (Console::isLoaded()) {
            Console::debug($msg1);
        }
    }
}
