<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Contract\Cache\CacheInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerMiddlewareInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\Message\HttpResponseInterface;
use Salient\Contract\Http\AccessTokenInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpRequestHandlerInterface;
use Salient\Core\Builder;
use Closure;
use Stringable;

/**
 * @method $this uri(PsrUriInterface|Stringable|string|null $value) Endpoint URI (cannot have query or fragment components)
 * @method $this headers(Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $value) Request headers
 * @method $this accessToken(AccessTokenInterface|null $value) Access token applied to request headers
 * @method $this accessTokenHeaderName(string $value) Name of access token header (default: `"Authorization"`)
 * @method $this sensitiveHeaders(string[] $value) Headers treated as sensitive (default: {@see Curler::HEADERS_SENSITIVE})
 * @method $this mediaType(string|null $value) Media type applied to request headers
 * @method $this userAgent(string|null $value) User agent applied to request headers
 * @method $this expectJson(bool $value = true) Explicitly accept JSON-encoded responses and assume responses with no content type contain JSON (default: true)
 * @method $this postJson(bool $value = true) Use JSON to encode POST/PUT/PATCH/DELETE data (default: true)
 * @method $this dateFormatter(DateFormatterInterface|null $value) Date formatter used to format and parse the endpoint's date and time values
 * @method $this formDataFlags(int-mask-of<Curler::PRESERVE_*> $value) Flags used to encode data for query strings and message bodies (default: {@see Curler::PRESERVE_NUMERIC_KEYS} `|` {@see Curler::PRESERVE_STRING_KEYS})
 * @method $this jsonDecodeFlags(int-mask-of<\JSON_BIGINT_AS_STRING|\JSON_INVALID_UTF8_IGNORE|\JSON_INVALID_UTF8_SUBSTITUTE|\JSON_OBJECT_AS_ARRAY|\JSON_THROW_ON_ERROR> $value) Flags used to decode JSON returned by the endpoint (default: {@see \JSON_OBJECT_AS_ARRAY})
 * @method $this middleware(array<array{CurlerMiddlewareInterface|HttpRequestHandlerInterface|Closure(RequestInterface $request, Closure(RequestInterface): HttpResponseInterface $next, CurlerInterface $curler): ResponseInterface,1?:string|null}> $value) Middleware applied to the request handler stack
 * @method $this pager(CurlerPagerInterface|null $value) Pagination handler
 * @method $this alwaysPaginate(bool $value = true) Use the pager to process requests even if no pagination is required (default: false)
 * @method $this cache(CacheInterface|null $value) Cache to use for cookie and response storage instead of the global cache
 * @method $this handleCookies(bool $value = true) Enable cookie handling (default: false)
 * @method $this cookiesCacheKey(string|null $value) Key to cache cookies under (cookie handling is implicitly enabled if given)
 * @method $this cacheResponses(bool $value = true) Cache responses to GET and HEAD requests (HTTP caching headers are ignored; USE RESPONSIBLY) (default: false)
 * @method $this cachePostResponses(bool $value = true) Cache responses to repeatable POST requests (ignored if GET and HEAD request caching is disabled) (default: false)
 * @method $this cacheKeyCallback((callable(RequestInterface $request, CurlerInterface $curler): (string[]|string))|null $value) Override values hashed and combined with request method and URI to create response cache keys (headers not in {@see Curler::HEADERS_UNSTABLE} are used by default)
 * @method $this cacheLifetime(int<-1,max> $value) Seconds before cached responses expire when caching is enabled (`0` = cache indefinitely; `-1` = do not cache; default: `3600`)
 * @method $this refreshCache(bool $value = true) Replace cached responses even if they haven't expired (default: false)
 * @method $this timeout(int<0,max>|null $value) Connection timeout in seconds (`null` = use underlying default of `300` seconds; default: `null`)
 * @method $this followRedirects(bool $value = true) Follow "Location" headers (default: false)
 * @method $this maxRedirects(int<-1,max>|null $value) Limit the number of "Location" headers followed (`-1` = unlimited; `0` = do not follow redirects; `null` = use underlying default of `30`; default: `null`)
 * @method $this retryAfterTooManyRequests(bool $value = true) Retry throttled requests when the endpoint returns a "Retry-After" header (default: false)
 * @method $this retryAfterMaxSeconds(int<0,max> $value) Limit the delay between request attempts (`0` = unlimited; default: `300`)
 * @method $this throwHttpErrors(bool $value = true) Throw exceptions for HTTP errors (default: true)
 * @method HttpHeadersInterface head(mixed[]|null $query = null) Send a HEAD request to the endpoint
 * @method mixed get(mixed[]|null $query = null) Send a GET request to the endpoint and return the body of the response
 * @method mixed post(mixed[]|object|null $data = null, mixed[]|null $query = null) Send a POST request to the endpoint and return the body of the response
 * @method mixed put(mixed[]|object|null $data = null, mixed[]|null $query = null) Send a PUT request to the endpoint and return the body of the response
 * @method mixed patch(mixed[]|object|null $data = null, mixed[]|null $query = null) Send a PATCH request to the endpoint and return the body of the response
 * @method mixed delete(mixed[]|object|null $data = null, mixed[]|null $query = null) Send a DELETE request to the endpoint and return the body of the response
 * @method iterable<mixed> getP(mixed[]|null $query = null) Send a GET request to the endpoint and iterate over response pages
 * @method iterable<mixed> postP(mixed[]|object|null $data = null, mixed[]|null $query = null) Send a POST request to the endpoint and iterate over response pages
 * @method iterable<mixed> putP(mixed[]|object|null $data = null, mixed[]|null $query = null) Send a PUT request to the endpoint and iterate over response pages
 * @method iterable<mixed> patchP(mixed[]|object|null $data = null, mixed[]|null $query = null) Send a PATCH request to the endpoint and iterate over response pages
 * @method iterable<mixed> deleteP(mixed[]|object|null $data = null, mixed[]|null $query = null) Send a DELETE request to the endpoint and iterate over response pages
 * @method mixed postR(string $data, string $mediaType, mixed[]|null $query = null) Send raw data to the endpoint in a POST request and return the body of the response
 * @method mixed putR(string $data, string $mediaType, mixed[]|null $query = null) Send raw data to the endpoint in a PUT request and return the body of the response
 * @method mixed patchR(string $data, string $mediaType, mixed[]|null $query = null) Send raw data to the endpoint in a PATCH request and return the body of the response
 * @method mixed deleteR(string $data, string $mediaType, mixed[]|null $query = null) Send raw data to the endpoint in a DELETE request and return the body of the response
 * @method ResponseInterface sendRequest(RequestInterface $request) Sends a PSR-7 request and returns a PSR-7 response
 *
 * @api
 *
 * @extends Builder<Curler>
 *
 * @generated
 */
final class CurlerBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return Curler::class;
    }

    /**
     * @internal
     */
    protected static function getStaticConstructor(): string
    {
        return 'create';
    }

    /**
     * @internal
     */
    protected static function getTerminators(): array
    {
        return [
            'head',
            'get',
            'post',
            'put',
            'patch',
            'delete',
            'getP',
            'postP',
            'putP',
            'patchP',
            'deleteP',
            'postR',
            'putR',
            'patchR',
            'deleteR',
            'sendRequest',
        ];
    }
}
