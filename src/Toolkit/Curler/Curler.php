<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Cache\CacheStoreInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Buildable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\JsonDecodeFlag;
use Salient\Contract\Core\MimeType;
use Salient\Contract\Core\QueryFlag;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerMiddlewareInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\AccessTokenInterface;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpHeaderGroup;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpMultipartStreamInterface;
use Salient\Contract\Http\HttpRequestHandlerInterface;
use Salient\Contract\Http\HttpRequestMethod as Method;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Contract\Http\UriInterface;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\LogicException;
use Salient\Core\Exception\OutOfRangeException;
use Salient\Core\Exception\RuntimeException;
use Salient\Core\Facade\Cache;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Http;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Str;
use Salient\Curler\Exception\CurlErrorException;
use Salient\Curler\Exception\HttpErrorException;
use Salient\Curler\Exception\NetworkException;
use Salient\Curler\Exception\RequestException;
use Salient\Http\Exception\InvalidHeaderException;
use Salient\Http\Exception\StreamEncapsulationException;
use Salient\Http\HasHttpHeaders;
use Salient\Http\HttpHeaders;
use Salient\Http\HttpRequest;
use Salient\Http\HttpResponse;
use Salient\Http\HttpStream;
use Salient\Http\Uri;
use Closure;
use CurlHandle;
use Generator;
use Stringable;
use Throwable;

/**
 * An HTTP client optimised for RESTful APIs
 *
 * @implements Buildable<CurlerBuilder>
 * @use HasBuilder<CurlerBuilder>
 */
class Curler implements CurlerInterface, Buildable
{
    /**
     * Limit input strings to 2MiB
     *
     * The underlying limit, `CURL_MAX_INPUT_LENGTH`, is 8MB.
     */
    protected const MAX_INPUT_LENGTH = 2 * 1024 ** 2;

    /** @phpstan-use HasBuilder<CurlerBuilder> */
    use HasBuilder;
    use HasHttpHeaders;
    use HasImmutableProperties {
        HasImmutableProperties::withPropertyValue as with;
        HasImmutableProperties::withoutProperty as without;
    }

    protected Uri $Uri;
    protected HttpHeadersInterface $Headers;
    protected ?AccessTokenInterface $AccessToken;
    protected string $AccessTokenHeaderName;
    /** @var array<string,true> */
    protected array $SensitiveHeaders;
    protected ?string $MediaType;
    protected string $UserAgent;
    protected bool $ExpectJson;
    protected bool $PostJson;
    protected ?DateFormatterInterface $DateFormatter;
    /** @var int-mask-of<QueryFlag::*> */
    protected int $QueryFlags;
    /** @var int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_OBJECT_AS_ARRAY> */
    protected int $JsonDecodeFlags;
    /** @var array<array{CurlerMiddlewareInterface|HttpRequestHandlerInterface|Closure(RequestInterface $request, Closure(RequestInterface): HttpResponseInterface $next, CurlerInterface $curler): ResponseInterface,string|null}> */
    protected array $Middleware = [];
    protected ?CurlerPagerInterface $Pager;
    protected bool $AlwaysPaginate;
    protected ?CacheStoreInterface $CacheStore;
    protected ?string $CookiesCacheKey;
    protected bool $CacheResponses;
    protected bool $CachePostResponses;
    /** @var (Closure(RequestInterface): (string[]|string))|null */
    protected ?Closure $CacheKeyClosure;
    /** @var int<-1,max> */
    protected int $CacheLifetime;
    protected bool $RefreshCache;
    /** @var int<0,max>|null */
    protected ?int $Timeout;
    protected bool $FollowRedirects;
    /** @var int<-1,max>|null */
    protected ?int $MaxRedirects;
    protected bool $RetryAfterTooManyRequests;
    /** @var int<0,max> */
    protected int $RetryAfterMaxSeconds;
    protected bool $ThrowHttpErrors;
    protected ?RequestInterface $LastRequest = null;
    protected ?HttpResponseInterface $LastResponse = null;
    private ?Curler $WithoutThrowHttpErrors = null;
    private ?Closure $Closure = null;
    private static string $DefaultUserAgent;
    /** @var CurlHandle|resource|null */
    private static $Handle;

    /**
     * Creates a new Curler object
     *
     * @param PsrUriInterface|Stringable|string|null $uri Endpoint URI (cannot have query or fragment components)
     * @param Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $headers Request headers
     * @param AccessTokenInterface|null $accessToken Access token applied to request headers
     * @param string $accessTokenHeaderName Name of access token header (default: `"Authorization"`)
     * @param string[] $sensitiveHeaders Headers treated as sensitive (default: {@see HttpHeaderGroup::SENSITIVE})
     * @param string|null $mediaType Media type applied to request headers
     * @param string|null $userAgent User agent applied to request headers
     * @param bool $expectJson Explicitly accept JSON-encoded responses and assume responses with no content type contain JSON
     * @param bool $postJson Use JSON to encode POST/PUT/PATCH/DELETE data
     * @param DateFormatterInterface|null $dateFormatter Date formatter used to format and parse the endpoint's date and time values
     * @param int-mask-of<QueryFlag::*> $queryFlags Flags used to encode data for query strings and `POST`/`PUT`/`PATCH`/`DELETE` bodies (default: {@see QueryFlag::PRESERVE_NUMERIC_KEYS} `|` {@see QueryFlag::PRESERVE_STRING_KEYS})
     * @param int-mask-of<JsonDecodeFlag::*> $jsonDecodeFlags Flags used to decode JSON returned by the endpoint (default: {@see JsonDecodeFlag::OBJECT_AS_ARRAY})
     * @param array<array{CurlerMiddlewareInterface|HttpRequestHandlerInterface|Closure(RequestInterface $request, Closure(RequestInterface): HttpResponseInterface $next, CurlerInterface $curler): ResponseInterface,1?:string|null}> $middleware Middleware applied to the request handler stack
     * @param CurlerPagerInterface|null $pager Pagination handler
     * @param bool $alwaysPaginate Use the pager to process requests even if no pagination is required
     * @param CacheStoreInterface|null $cacheStore Cache store used for cookie and response caching instead of the {@see Cache} facade's underlying store
     * @param bool $handleCookies Enable cookie handling
     * @param string|null $cookiesCacheKey Key to cache cookies under (cookie handling is implicitly enabled if given)
     * @param bool $cacheResponses Cache responses to GET and HEAD requests (HTTP caching headers are ignored; USE RESPONSIBLY)
     * @param bool $cachePostResponses Cache responses to repeatable POST requests (ignored if GET and HEAD request caching is disabled)
     * @param (callable(RequestInterface): (string[]|string))|null $cacheKeyCallback Override values hashed and combined with request method and URI to create response cache keys (headers returned by {@see Curler::getPublicHttpHeaders()} are used by default)
     * @param int<-1,max> $cacheLifetime Seconds before cached responses expire when caching is enabled (`0` = cache indefinitely; `-1` = do not cache; default: `3600`)
     * @param bool $refreshCache Replace cached responses even if they haven't expired
     * @param int<0,max>|null $timeout Connection timeout in seconds (`null` = use underlying default of `300` seconds; default: `null`)
     * @param bool $followRedirects Follow "Location" headers
     * @param int<-1,max>|null $maxRedirects Limit the number of "Location" headers followed (`-1` = unlimited; `0` = do not follow redirects; `null` = use underlying default of `20`; default: `null`)
     * @param bool $retryAfterTooManyRequests Retry throttled requests when the endpoint returns a "Retry-After" header
     * @param int<0,max> $retryAfterMaxSeconds Limit the delay between request attempts (`0` = unlimited; default: `300`)
     * @param bool $throwHttpErrors Throw exceptions for HTTP errors
     */
    public function __construct(
        $uri = null,
        $headers = null,
        ?AccessTokenInterface $accessToken = null,
        string $accessTokenHeaderName = HttpHeader::AUTHORIZATION,
        array $sensitiveHeaders = HttpHeaderGroup::SENSITIVE,
        ?string $mediaType = null,
        ?string $userAgent = null,
        bool $expectJson = true,
        bool $postJson = true,
        ?DateFormatterInterface $dateFormatter = null,
        int $queryFlags = QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
        int $jsonDecodeFlags = JsonDecodeFlag::OBJECT_AS_ARRAY,
        array $middleware = [],
        ?CurlerPagerInterface $pager = null,
        bool $alwaysPaginate = false,
        ?CacheStoreInterface $cacheStore = null,
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
    ) {
        $this->Uri = $this->filterUri($uri);
        $this->Headers = $this->filterHeaders($headers);
        $this->AccessToken = $accessToken;
        if ($accessToken !== null) {
            $this->AccessTokenHeaderName = $accessTokenHeaderName;
        }
        $this->SensitiveHeaders = array_change_key_case(
            array_fill_keys($sensitiveHeaders, true),
        );
        $this->MediaType = $mediaType;
        $this->UserAgent = $userAgent ?? $this->getDefaultUserAgent();
        $this->ExpectJson = $expectJson;
        $this->PostJson = $postJson;
        $this->DateFormatter = $dateFormatter;
        $this->QueryFlags = $queryFlags;
        $this->JsonDecodeFlags = $jsonDecodeFlags;
        foreach ($middleware as $value) {
            $this->Middleware[] = [$value[0], $value[1] ?? null];
        }
        $this->Pager = $pager;
        $this->AlwaysPaginate = $pager && $alwaysPaginate;
        $this->CacheStore = $cacheStore;
        $this->CookiesCacheKey = $handleCookies || $cookiesCacheKey !== null
            ? $this->filterCookiesCacheKey($cookiesCacheKey)
            : null;
        $this->CacheResponses = $cacheResponses;
        $this->CachePostResponses = $cachePostResponses;
        $this->CacheKeyClosure = Get::closure($cacheKeyCallback);
        $this->CacheLifetime = $cacheLifetime;
        $this->RefreshCache = $refreshCache;
        $this->Timeout = $timeout;
        $this->FollowRedirects = $followRedirects;
        $this->MaxRedirects = $maxRedirects;
        $this->RetryAfterTooManyRequests = $retryAfterTooManyRequests;
        $this->RetryAfterMaxSeconds = $retryAfterMaxSeconds;
        $this->ThrowHttpErrors = $throwHttpErrors;
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
    public function getUriWithQuery($query): UriInterface
    {
        if ($query === null || $query === [] || $query === '') {
            return $this->Uri;
        }

        return $this->Uri->withQuery(
            is_array($query)
                ? Get::query($query, $this->QueryFlags, $this->DateFormatter)
                : $query
        );
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

        $headers = $this->LastResponse->getHttpHeaders();
        if (!$headers->hasHeader(HttpHeader::CONTENT_TYPE)) {
            return $this->ExpectJson;
        }

        try {
            return Http::mediaTypeIs(
                $headers->getOneHeaderLine(HttpHeader::CONTENT_TYPE),
                MimeType::JSON
            );
        } catch (InvalidHeaderException $ex) {
            Console::debug($ex->getMessage());
            return false;
        }
    }

    // --

    /**
     * @inheritDoc
     */
    public function head(?array $query = null): HttpHeadersInterface
    {
        return $this->process(Method::HEAD, $query);
    }

    /**
     * @inheritDoc
     */
    public function get(?array $query = null)
    {
        return $this->process(Method::GET, $query);
    }

    /**
     * @inheritDoc
     */
    public function post($data = null, ?array $query = null)
    {
        return $this->process(Method::POST, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function put($data = null, ?array $query = null)
    {
        return $this->process(Method::PUT, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function patch($data = null, ?array $query = null)
    {
        return $this->process(Method::PATCH, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function delete($data = null, ?array $query = null)
    {
        return $this->process(Method::DELETE, $query, $data);
    }

    /**
     * @template TMethod of Method::GET|Method::HEAD|Method::POST|Method::PUT|Method::PATCH|Method::DELETE
     *
     * @param string&TMethod $method
     * @param mixed[]|null $query
     * @param mixed[]|object|false|null $data
     * @return (TMethod is Method::HEAD ? HttpHeadersInterface : mixed)
     */
    private function process(string $method, ?array $query, $data = false)
    {
        $request = $this->createRequest($method, $query, $data);

        $pager = $this->AlwaysPaginate ? $this->Pager : null;
        if ($pager) {
            $request = $pager->getFirstRequest($request, $this);
        }

        $this->doSendRequest($request);

        if ($method === Method::HEAD) {
            return $this->LastResponse->getHttpHeaders();
        }

        $result = $this->getLastResponseBody();

        if ($pager) {
            $page = $pager->getPage($result, $request, $this);
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
        return $this->paginate(Method::GET, $query);
    }

    /**
     * @inheritDoc
     */
    public function postP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(Method::POST, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function putP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(Method::PUT, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function patchP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(Method::PATCH, $query, $data);
    }

    /**
     * @inheritDoc
     */
    public function deleteP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(Method::DELETE, $query, $data);
    }

    /**
     * @param mixed[]|null $query
     * @param mixed[]|object|false|null $data
     * @return Generator<mixed>
     */
    private function paginate(string $method, ?array $query, $data = false): Generator
    {
        if ($this->Pager === null) {
            throw new LogicException('No pager');
        }

        $request = $this->createRequest($method, $query, $data);
        $request = $this->Pager->getFirstRequest($request, $this);
        $prev = null;
        do {
            $this->doSendRequest($request);
            $page = $this->Pager->getPage(
                $this->getLastResponseBody(),
                $request,
                $this,
                $prev,
            );
            yield from $page->getEntities();
            if ($page->isLastPage()) {
                break;
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
        return $this->processRaw(Method::POST, $data, $mediaType, $query);
    }

    /**
     * @inheritDoc
     */
    public function putR(string $data, string $mediaType, ?array $query = null)
    {
        return $this->processRaw(Method::PUT, $data, $mediaType, $query);
    }

    /**
     * @inheritDoc
     */
    public function patchR(string $data, string $mediaType, ?array $query = null)
    {
        return $this->processRaw(Method::PATCH, $data, $mediaType, $query);
    }

    /**
     * @inheritDoc
     */
    public function deleteR(string $data, string $mediaType, ?array $query = null)
    {
        return $this->processRaw(Method::DELETE, $data, $mediaType, $query);
    }

    /**
     * @param mixed[]|null $query
     * @return mixed
     */
    private function processRaw(string $method, string $data, string $mediaType, ?array $query)
    {
        $request = $this->createRequest($method, $query, $data);
        $request = $request->withHeader(HttpHeader::CONTENT_TYPE, $mediaType);
        $this->doSendRequest($request);
        return $this->getLastResponseBody();
    }

    // --

    /**
     * @inheritDoc
     */
    public function flushCookies()
    {
        if ($this->CookiesCacheKey !== null) {
            $this->getCache()->delete($this->CookiesCacheKey);
        }
        return $this;
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
            $headers = $headers->set(HttpHeader::CONTENT_TYPE, $this->MediaType);
        }

        if ($this->ExpectJson) {
            $headers = $headers->set(HttpHeader::ACCEPT, MimeType::JSON);
        }

        return $headers->set(HttpHeader::USER_AGENT, $this->UserAgent);
    }

    /**
     * @inheritDoc
     */
    public function getPublicHttpHeaders(): HttpHeadersInterface
    {
        return $this->getHttpHeaders()->exceptIn($this->SensitiveHeaders);
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
    public function getUserAgent(): string
    {
        return $this->UserAgent;
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
    public function getPager(): ?CurlerPagerInterface
    {
        return $this->Pager;
    }

    /**
     * @inheritDoc
     */
    public function getCacheStore(): ?CacheStoreInterface
    {
        return $this->CacheStore;
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
    public function withAccessToken(
        ?AccessTokenInterface $token,
        string $headerName = HttpHeader::AUTHORIZATION
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
        return $this->with(
            'UserAgent',
            $userAgent ?? $this->getDefaultUserAgent()
        );
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
    public function withQueryFlags(int $flags)
    {
        return $this->with('QueryFlags', $flags);
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
        $i = is_string($middleware) ? 1 : 0;
        $values = $this->Middleware;
        foreach ($values as $key => $value) {
            if ($middleware === $value[$i]) {
                unset($values[$key]);
            }
        }
        return $this->with('Middleware', $values);
    }

    /**
     * @inheritDoc
     */
    public function withPager(
        ?CurlerPagerInterface $pager,
        bool $alwaysPaginate = false
    ) {
        return $this
            ->with('Pager', $pager)
            ->with('AlwaysPaginate', $pager && $alwaysPaginate);
    }

    /**
     * @inheritDoc
     */
    public function withCacheStore(?CacheStoreInterface $store = null)
    {
        return $this->with('CacheStore', $store);
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
        return $this->with('CacheKeyClosure', Get::closure($callback));
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
        $curler = $this->WithoutThrowHttpErrors
            ??= $this->withThrowHttpErrors(false);
        try {
            try {
                return $curler->doSendRequest($request);
            } finally {
                $this->LastRequest = $curler->LastRequest;
                $this->LastResponse = $curler->LastResponse;
            }
        } catch (ClientExceptionInterface $ex) {
            throw $ex;
        } catch (CurlErrorException $ex) {
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
                    $middleware($request, $closure, $this)
                : ($middleware instanceof HttpRequestHandlerInterface
                    ? fn(RequestInterface $request): HttpResponseInterface =>
                        $this->getHttpResponse($middleware($request, $closure))
                    : fn(RequestInterface $request): HttpResponseInterface =>
                        $this->getHttpResponse($middleware($request, $closure, $this)));
        }

        return $closure;
    }

    private function getResponse(RequestInterface $request): HttpResponseInterface
    {
        $uri = $request->getUri()->withFragment('');
        $request = $request->withUri($uri);

        if (self::$Handle === null) {
            $handle = curl_init((string) $uri);
            if ($handle === false) {
                throw new RuntimeException('curl_init() failed');
            }
            self::$Handle = $handle;
        } else {
            curl_reset(self::$Handle);
            $opt[\CURLOPT_URL] = (string) $uri;
        }

        $version = (int) ((float) $request->getProtocolVersion() * 10);
        $opt[\CURLOPT_HTTP_VERSION] = [
            10 => \CURL_HTTP_VERSION_1_0,
            11 => \CURL_HTTP_VERSION_1_1,
            20 => \CURL_HTTP_VERSION_2_0,
        ][$version] ?? \CURL_HTTP_VERSION_NONE;

        $method = $request->getMethod();
        $opt[\CURLOPT_CUSTOMREQUEST] = $method;
        if ($method === Method::HEAD) {
            $opt[\CURLOPT_NOBODY] = true;
        }

        $body = $request->getBody();
        $size = $body->getSize();
        if ($size === null || $size > 0) {
            $size ??= HttpHeaders::from($request)->getContentLength();
            if ($size !== null && $size <= static::MAX_INPUT_LENGTH) {
                $body = (string) $body;
                $opt[\CURLOPT_POSTFIELDS] = $body;
                $request = $request
                    ->withoutHeader(HttpHeader::CONTENT_LENGTH)
                    ->withoutHeader(HttpHeader::TRANSFER_ENCODING);
            } else {
                $opt[\CURLOPT_UPLOAD] = true;
                if ($size !== null) {
                    $opt[\CURLOPT_INFILESIZE] = $size;
                    $request = $request
                        ->withoutHeader(HttpHeader::CONTENT_LENGTH);
                }
                $opt[\CURLOPT_READFUNCTION] =
                    static function ($handle, $infile, int $length) use ($body): string {
                        return $body->read($length);
                    };
                if ($body->isSeekable()) {
                    $body->rewind();
                }
            }
        } elseif ([
            Method::POST => true,
            Method::PUT => true,
            Method::PATCH => true,
            Method::DELETE => true,
        ][$method] ?? false) {
            // [RFC7230], Section 3.3.2: "A user agent SHOULD send a
            // Content-Length in a request message when no Transfer-Encoding is
            // sent and the request method defines a meaning for an enclosed
            // payload body. For example, a Content-Length header field is
            // normally sent in a POST request even when the value is 0
            // (indicating an empty payload body)."
            $request = $request->withHeader(HttpHeader::CONTENT_LENGTH, '0');
        }

        if ($this->Timeout !== null) {
            $opt[\CURLOPT_CONNECTTIMEOUT] = $this->Timeout;
        }

        if ($this->FollowRedirects) {
            $opt[\CURLOPT_FOLLOWLOCATION] = true;
            if ($this->MaxRedirects !== null) {
                $opt[\CURLOPT_MAXREDIRS] = $this->MaxRedirects;
            }
        }

        if (!$request->hasHeader(HttpHeader::ACCEPT_ENCODING)) {
            // Enable all supported encodings (e.g. gzip, deflate) and set
            // `Accept-Encoding` accordingly
            $opt[\CURLOPT_ENCODING] = '';
        }

        // Remove `Host` if its value is redundant
        if (($host = $request->getHeaderLine(HttpHeader::HOST)) !== '') {
            try {
                $host = new Uri("//$host");
            } catch (InvalidArgumentException $ex) {
                throw new InvalidHeaderException(sprintf(
                    'Invalid value for HTTP request header %s: %s',
                    HttpHeader::HOST,
                    $host,
                ), $ex);
            }
            $host = $host->withScheme($uri->getScheme())->getAuthority();
            if ($host === Uri::from($uri)->withUserInfo('')->getAuthority()) {
                $request = $request->withoutHeader(HttpHeader::HOST);
            }
        }

        $headers = HttpHeaders::from($request);

        $cacheKey = null;
        if (
            $this->CacheResponses
            && ($size === 0 || is_string($body))
            && ([Method::GET => true, Method::HEAD => true, Method::POST => $this->CachePostResponses][$method] ?? false)
        ) {
            $cacheKey = $this->CacheKeyClosure
                ? (array) ($this->CacheKeyClosure)($request)
                : $headers->exceptIn($this->SensitiveHeaders)->getLines('%s:%s');

            if ($size !== 0 || $method === Method::POST) {
                $cacheKey[] = $size === 0 ? '' : $body;
            }

            $cacheKey = implode(':', [
                self::class,
                'response',
                $method,
                rawurlencode((string) $uri),
                Get::hash(implode("\0", $cacheKey)),
            ]);

            if (
                !$this->RefreshCache
                && ($last = $this->getCache()->getArray($cacheKey)) !== null
            ) {
                /** @var array{code:int,body:string,headers:HttpHeadersInterface,reason:string|null,version:string}|array{int,string,HttpHeadersInterface,string} $last */
                return $this->LastResponse = new HttpResponse(
                    $last['code'] ?? $last[0] ?? 200,
                    $last['body'] ?? $last[3] ?? null,
                    $last['headers'] ?? $last[2] ?? null,
                    $last['reason'] ?? $last[1] ?? null,
                    $last['version'] ?? '1.1',
                );
            }
        }

        $opt[\CURLOPT_HTTPHEADER] = $headers->getLines('%s: %s', '%s;');

        /** @var string|null */
        $statusLine = null;
        /** @var HttpHeaders|null */
        $headers = null;
        $opt[\CURLOPT_HEADERFUNCTION] =
            static function ($handle, string $header) use (&$statusLine, &$headers): int {
                if (substr($header, 0, 5) === 'HTTP/') {
                    $statusLine = rtrim($header, "\r\n");
                    $headers = new HttpHeaders();
                    return strlen($header);
                }
                if ($headers === null) {
                    throw new InvalidHeaderException('No status line in HTTP response');
                }
                $headers = $headers->addLine($header);
                return strlen($header);
            };

        $body = new HttpStream(File::open('php://temp', 'r+'));
        $opt[\CURLOPT_WRITEFUNCTION] =
            static function ($handle, string $data) use ($body): int {
                return $body->write($data);
            };

        curl_setopt_array(self::$Handle, $opt);

        if ($this->CookiesCacheKey !== null) {
            // "If the name is an empty string, no cookies are loaded, but
            // cookie handling is still enabled"
            curl_setopt(self::$Handle, \CURLOPT_COOKIEFILE, '');
            $cookies = $this->getCache()->getArray($this->CookiesCacheKey);
            if ($cookies !== null) {
                foreach ($cookies as $cookie) {
                    curl_setopt(self::$Handle, \CURLOPT_COOKIELIST, $cookie);
                }
            }
        }

        $this->LastRequest = $request;

        $attempts = 0;
        do {
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

            /** @var HttpHeaders $headers */
            $code = (int) $split[1];
            $reason = $split[2] ?? null;

            if (
                !$this->RetryAfterTooManyRequests
                || $attempts
                || $code !== 429
                || ($after = $headers->getRetryAfter()) === null
                || ($this->RetryAfterMaxSeconds !== 0 && $after > $this->RetryAfterMaxSeconds)
            ) {
                break;
            }

            $after = max(1, $after);
            Console::debug(Inflect::format($after, 'Sleeping for {{#}} {{#:second}}'));
            sleep($after);
            $attempts++;
        } while (true);

        if ($this->CookiesCacheKey !== null) {
            $this->getCache()->set(
                $this->CookiesCacheKey,
                curl_getinfo(self::$Handle, \CURLINFO_COOKIELIST)
            );
        }

        $response = new HttpResponse($code, $body, $headers, $reason, $version);
        $this->LastResponse = $response;

        if ($this->ThrowHttpErrors && $code >= 400) {
            throw new HttpErrorException($request, $response, $this->getCurlInfo());
        }

        if ($cacheKey !== null && $this->CacheLifetime >= 0) {
            $ttl = $this->CacheLifetime === 0
                ? null
                : $this->CacheLifetime;
            $this->getCache()->set(
                $cacheKey,
                [
                    'code' => $code,
                    'body' => (string) $body,
                    'headers' => $headers,
                    'reason' => $reason,
                    'version' => $version,
                ],
                $ttl,
            );
        }

        return $response;
    }

    // --

    /**
     * @param mixed[]|null $query
     * @param mixed[]|object|string|false|null $data
     */
    private function createRequest(string $method, ?array $query, $data): HttpRequest
    {
        $uri = $this->getUriWithQuery($query);
        $headers = $this->getHttpHeaders();
        $request = new HttpRequest($method, $uri, null, $headers);
        if ($data !== false) {
            return $this->applyData($request, $data);
        }
        return $request;
    }

    /**
     * @param mixed[]|object|string|null $data
     */
    private function applyData(HttpRequest $request, $data): HttpRequest
    {
        if ($data === null) {
            if ($this->PostJson) {
                Console::debug(sprintf(
                    'JSON message bodies cannot be empty; falling back to %s',
                    MimeType::FORM,
                ));
            }
            return $request->withHeader(HttpHeader::CONTENT_TYPE, MimeType::FORM);
        }
        if (is_string($data)) {
            return $request->withBody(HttpStream::fromString($data));
        }
        if ($this->PostJson) {
            try {
                $body = HttpStream::fromData($data, $this->QueryFlags, $this->DateFormatter, true);
                $mediaType = MimeType::JSON;
            } catch (StreamEncapsulationException $ex) {
                Console::debug(sprintf(
                    '%s; falling back to %s',
                    $ex->getMessage(),
                    MimeType::FORM_MULTIPART,
                ));
            }
        }
        $body ??= HttpStream::fromData($data, $this->QueryFlags, $this->DateFormatter);
        $mediaType ??= $body instanceof HttpMultipartStreamInterface
            ? MimeType::FORM_MULTIPART
            : MimeType::FORM;
        return $request
            ->withHeader(HttpHeader::CONTENT_TYPE, $mediaType)
            ->withBody($body);
    }

    /**
     * @return mixed
     */
    private function getLastResponseBody()
    {
        assert($this->LastResponse !== null);
        $body = (string) $this->LastResponse->getBody();
        return $this->lastResponseIsJson()
            ? ($body === ''
                ? null
                : Json::parse($body, $this->JsonDecodeFlags))
            : $body;
    }

    private function getHttpResponse(ResponseInterface $response): HttpResponseInterface
    {
        return $response instanceof HttpResponseInterface
            ? $response
            : HttpResponse::fromPsr7($response);
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

    private function filterCookiesCacheKey(?string $cacheKey): string
    {
        return Arr::implode(':', [self::class, 'cookies', $cacheKey]);
    }

    private function getCache(): CacheStoreInterface
    {
        return $this->CacheStore ?? Cache::getInstance();
    }

    private function getDefaultUserAgent(): string
    {
        return self::$DefaultUserAgent ??= Http::getProduct();
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
}
