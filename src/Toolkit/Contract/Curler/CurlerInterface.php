<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Cache\CacheStoreInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Http\AccessTokenInterface;
use Salient\Contract\Http\FormDataFlag;
use Salient\Contract\Http\HttpHeader;
use Salient\Contract\Http\HttpHeaderGroup;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpRequestHandlerInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Salient\Contract\Http\UriInterface;
use Closure;
use Stringable;

interface CurlerInterface extends ClientInterface
{
    /**
     * Get the URI of the endpoint
     */
    public function getUri(): UriInterface;

    /**
     * Apply the given query string to a copy of the endpoint's URI
     *
     * @param mixed[]|string|null $query
     */
    public function getUriWithQuery($query): UriInterface;

    /**
     * Get an instance with the given endpoint URI
     *
     * An exception is thrown if the endpoint URI has a query or fragment.
     *
     * @param PsrUriInterface|Stringable|string|null $uri
     * @return static
     */
    public function withUri($uri);

    /**
     * Apply the URI and headers of the given request to a copy of the instance
     *
     * @return static
     */
    public function withRequest(RequestInterface $request);

    /**
     * Get the last request sent to the endpoint or passed to middleware
     */
    public function getLastRequest(): ?RequestInterface;

    /**
     * Get the last response received from the endpoint or returned by
     * middleware
     */
    public function getLastResponse(): ?HttpResponseInterface;

    /**
     * Check if the last response contains JSON-encoded data
     */
    public function lastResponseIsJson(): bool;

    // --

    /**
     * Send a HEAD request to the endpoint
     *
     * @param mixed[]|null $query
     */
    public function head(?array $query = null): HttpHeadersInterface;

    /**
     * Send a GET request to the endpoint and return the body of the response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function get(?array $query = null);

    /**
     * Send a POST request to the endpoint and return the body of the response
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function post($data = null, ?array $query = null);

    /**
     * Send a PUT request to the endpoint and return the body of the response
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function put($data = null, ?array $query = null);

    /**
     * Send a PATCH request to the endpoint and return the body of the response
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function patch($data = null, ?array $query = null);

    /**
     * Send a DELETE request to the endpoint and return the body of the response
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function delete($data = null, ?array $query = null);

    // --

    /**
     * Send a GET request to the endpoint and iterate over response pages
     *
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function getP(?array $query = null): iterable;

    /**
     * Send a POST request to the endpoint and iterate over response pages
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function postP($data = null, ?array $query = null): iterable;

    /**
     * Send a PUT request to the endpoint and iterate over response pages
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function putP($data = null, ?array $query = null): iterable;

    /**
     * Send a PATCH request to the endpoint and iterate over response pages
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function patchP($data = null, ?array $query = null): iterable;

    /**
     * Send a DELETE request to the endpoint and iterate over response pages
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function deleteP($data = null, ?array $query = null): iterable;

    // --

    /**
     * Send raw data to the endpoint in a POST request and return the body of
     * the response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function postR(string $data, string $mediaType, ?array $query = null);

    /**
     * Send raw data to the endpoint in a PUT request and return the body of the
     * response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function putR(string $data, string $mediaType, ?array $query = null);

    /**
     * Send raw data to the endpoint in a PATCH request and return the body of
     * the response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function patchR(string $data, string $mediaType, ?array $query = null);

    /**
     * Send raw data to the endpoint in a DELETE request and return the body of
     * the response
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    public function deleteR(string $data, string $mediaType, ?array $query = null);

    // --

    /**
     * Invalidate cached cookies
     *
     * Calling this method has no effect if the instance does not handle
     * cookies.
     *
     * @return $this
     */
    public function flushCookies();

    // --

    /**
     * Get request headers
     */
    public function getHttpHeaders(): HttpHeadersInterface;

    /**
     * Get request headers that are not considered sensitive
     */
    public function getPublicHttpHeaders(): HttpHeadersInterface;

    /**
     * Get an array that maps request header names to values
     *
     * @return array<string,string[]>
     */
    public function getHeaders(): array;

    /**
     * Check if a request header exists
     */
    public function hasHeader(string $name): bool;

    /**
     * Get the value of a request header as a list of values
     *
     * @return string[]
     */
    public function getHeader(string $name): array;

    /**
     * Get the value of a request header as a list of values, splitting any
     * comma-separated values
     *
     * @return string[]
     */
    public function getHeaderValues(string $name): array;

    /**
     * Get the comma-separated values of a request header
     */
    public function getHeaderLine(string $name): string;

    /**
     * Get the first value of a request header after splitting any
     * comma-separated values
     */
    public function getFirstHeaderLine(string $name): string;

    /**
     * Get the last value of a request header after splitting any
     * comma-separated values
     */
    public function getLastHeaderLine(string $name): string;

    /**
     * Get the only value of a request header after splitting any
     * comma-separated values
     *
     * An exception is thrown if the request header has more than one value.
     */
    public function getOneHeaderLine(string $name): string;

    /**
     * Get an instance with a value applied to a request header, replacing any
     * existing values
     *
     * @param string[]|string $value
     * @return static
     */
    public function withHeader(string $name, $value);

    /**
     * Get an instance with a value applied to a request header, preserving any
     * existing values
     *
     * @param string[]|string $value
     * @return static
     */
    public function withAddedHeader(string $name, $value);

    /**
     * Get an instance with a request header removed
     *
     * @return static
     */
    public function withoutHeader(string $name);

    /**
     * Check if the instance has an access token
     */
    public function hasAccessToken(): bool;

    /**
     * Get an instance that applies an access token to request headers
     *
     * @return static
     */
    public function withAccessToken(
        ?AccessTokenInterface $token,
        string $headerName = HttpHeader::AUTHORIZATION
    );

    /**
     * Check if a header is considered sensitive
     */
    public function isSensitiveHeader(string $name): bool;

    /**
     * Get an instance that treats a header as sensitive
     *
     * Headers in {@see HttpHeaderGroup::SENSITIVE} are considered sensitive by
     * default.
     *
     * @return static
     */
    public function withSensitiveHeader(string $name);

    /**
     * Get an instance that does not treat the given header as sensitive
     *
     * @return static
     */
    public function withoutSensitiveHeader(string $name);

    /**
     * Get the media type applied to request headers
     */
    public function getMediaType(): ?string;

    /**
     * Get an instance that applies the given media type to request headers
     *
     * If `$type` is `null` (the default), `Content-Type` headers are
     * automatically applied to requests as needed.
     *
     * @return static
     */
    public function withMediaType(?string $type);

    /**
     * Get the current user agent string
     */
    public function getUserAgent(): string;

    /**
     * Get an instance with the given user agent string
     *
     * If `$userAgent` is `null`, the default user agent string is restored.
     *
     * @return static
     */
    public function withUserAgent(?string $userAgent);

    /**
     * Check if the instance explicitly accepts JSON-encoded responses and
     * assumes responses with no content type contain JSON
     */
    public function expectsJson(): bool;

    /**
     * Get an instance that explicitly accepts JSON-encoded responses and
     * assumes responses with no content type contain JSON
     *
     * @return static
     */
    public function withExpectJson(bool $expectJson = true);

    /**
     * Check if the instance uses JSON to encode POST/PUT/PATCH/DELETE data
     */
    public function postsJson(): bool;

    /**
     * Get an instance that uses JSON to encode POST/PUT/PATCH/DELETE data
     *
     * @return static
     */
    public function withPostJson(bool $postJson = true);

    /**
     * Get the date formatter applied to the instance
     */
    public function getDateFormatter(): ?DateFormatterInterface;

    /**
     * Get an instance that uses the given date formatter to format and parse
     * the endpoint's date and time values
     *
     * @return static
     */
    public function withDateFormatter(?DateFormatterInterface $formatter);

    /**
     * Get an instance with the given form data flags
     *
     * Form data flags are used to encode data for query strings and message
     * bodies.
     *
     * {@see FormDataFlag::PRESERVE_NUMERIC_KEYS} and
     * {@see FormDataFlag::PRESERVE_STRING_KEYS} are applied by default.
     *
     * @param int-mask-of<FormDataFlag::*> $flags
     * @return static
     */
    public function withFormDataFlags(int $flags);

    /**
     * Get an instance with the given json_decode() flags
     *
     * {@see \JSON_OBJECT_AS_ARRAY} is applied by default.
     * {@see \JSON_THROW_ON_ERROR} is always applied and cannot be disabled.
     *
     * @param int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_OBJECT_AS_ARRAY|\JSON_THROW_ON_ERROR> $flags
     * @return static
     */
    public function withJsonDecodeFlags(int $flags);

    /**
     * Get an instance with the given middleware applied to the request handler
     * stack
     *
     * @param CurlerMiddlewareInterface|HttpRequestHandlerInterface|Closure(RequestInterface $request, Closure(RequestInterface): HttpResponseInterface $next, CurlerInterface $curler): ResponseInterface $middleware
     * @return static
     */
    public function withMiddleware($middleware, ?string $name = null);

    /**
     * Get an instance where the given middleware is not applied to requests
     *
     * @param CurlerMiddlewareInterface|HttpRequestHandlerInterface|Closure|string $middleware
     * @return static
     */
    public function withoutMiddleware($middleware);

    /**
     * Get the endpoint's pagination handler
     */
    public function getPager(): ?CurlerPagerInterface;

    /**
     * Get an instance with the given pagination handler
     *
     * @param bool $alwaysPaginate If `true`, the pager is used to process
     * requests even if no pagination is required.
     * @return static
     */
    public function withPager(?CurlerPagerInterface $pager, bool $alwaysPaginate = false);

    /**
     * Get the endpoint's cache store
     */
    public function getCacheStore(): ?CacheStoreInterface;

    /**
     * Get an instance with the given cache store
     *
     * If no `$store` is given, cookies and responses are cached in the default
     * cache store as needed.
     *
     * @return static
     */
    public function withCacheStore(?CacheStoreInterface $store = null);

    /**
     * Check if the instance handles cookies
     */
    public function hasCookies(): bool;

    /**
     * Get an instance that handles cookies
     *
     * @return static
     */
    public function withCookies(?string $cacheKey = null);

    /**
     * Get an instance that does not handle cookies
     *
     * @return static
     */
    public function withoutCookies();

    /**
     * Check if response caching is enabled
     */
    public function hasResponseCache(): bool;

    /**
     * Get an instance that caches responses to GET and HEAD requests
     *
     * HTTP caching headers are ignored. USE RESPONSIBLY.
     *
     * @return static
     */
    public function withResponseCache(bool $cacheResponses = true);

    /**
     * Check if POST response caching is enabled
     */
    public function hasPostResponseCache(): bool;

    /**
     * Get an instance that caches responses to repeatable POST requests
     *
     * {@see withResponseCache()} must also be called to enable caching.
     *
     * @return static
     */
    public function withPostResponseCache(bool $cachePostResponses = true);

    /**
     * Get an instance that uses a callback to generate response cache keys
     *
     * The callback's return value is hashed and combined with request method
     * and URI to create a response cache key.
     *
     * @param (callable(RequestInterface $request, CurlerInterface $curler): (string[]|string))|null $callback
     * @return static
     */
    public function withCacheKeyCallback(?callable $callback);

    /**
     * Get the lifetime of cached responses, in seconds
     *
     * @return int<-1,max>
     */
    public function getCacheLifetime(): int;

    /**
     * Get an instance where cached responses expire after the given number of
     * seconds
     *
     * `3600` is applied by default.
     *
     * {@see withResponseCache()} must also be called to enable caching.
     *
     * @param int<-1,max> $seconds - `0`: cache responses indefinitely
     * - `-1`: disable caching until the method is called again with `$seconds`
     *   greater than or equal to `0`
     * @return static
     */
    public function withCacheLifetime(int $seconds);

    /**
     * Check if the instance replaces cached responses even if they haven't
     * expired
     */
    public function refreshesCache(): bool;

    /**
     * Get an instance that replaces cached responses even if they haven't
     * expired
     *
     * @return static
     */
    public function withRefreshCache(bool $refresh = true);

    /**
     * Get the connection timeout applied to the instance, in seconds
     *
     * @return int<0,max>|null
     */
    public function getTimeout(): ?int;

    /**
     * Get an instance with the given connection timeout
     *
     * @param int<0,max>|null $seconds - `0`: wait indefinitely
     * - `null` (default): use the underlying client's default connection
     *   timeout
     * @return static
     */
    public function withTimeout(?int $seconds);

    /**
     * Check if the instance follows "Location" headers
     */
    public function followsRedirects(): bool;

    /**
     * Get an instance that follows "Location" headers
     *
     * @return static
     */
    public function withFollowRedirects(bool $follow = true);

    /**
     * Get the maximum number of "Location" headers followed
     *
     * @return int<-1,max>|null
     */
    public function getMaxRedirects(): ?int;

    /**
     * Get an instance that limits the number of "Location" headers followed
     *
     * @param int<-1,max>|null $redirects - `-1`: allow unlimited redirects
     * - `0`: disable redirects (same effect as `withFollowRedirects(false)`)
     * - `null` (default): use the underlying client's default redirect limit
     * @return static
     */
    public function withMaxRedirects(?int $redirects);

    /**
     * Check if the instance retries throttled requests when the endpoint
     * returns a "Retry-After" header
     */
    public function getRetryAfterTooManyRequests(): bool;

    /**
     * Get the maximum delay between request attempts
     *
     * @return int<0,max>
     */
    public function getRetryAfterMaxSeconds(): int;

    /**
     * Get an instance that retries throttled requests when the endpoint returns
     * a "Retry-After" header
     *
     * @return static
     */
    public function withRetryAfterTooManyRequests(bool $retry = true);

    /**
     * Get an instance that limits the delay between request attempts
     *
     * `300` is applied by default.
     *
     * @param int<0,max> $seconds If `0`, unlimited delays are allowed.
     * @return static
     */
    public function withRetryAfterMaxSeconds(int $seconds);

    /**
     * Check if exceptions are thrown for HTTP errors
     */
    public function throwsHttpErrors(): bool;

    /**
     * Get an instance that throws exceptions for HTTP errors
     *
     * @return static
     */
    public function withThrowHttpErrors(bool $throw = true);
}
