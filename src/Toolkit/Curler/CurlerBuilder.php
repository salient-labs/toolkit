<?php declare(strict_types=1);

namespace Salient\Curler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;
use Salient\Cache\CacheStore;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\JsonDecodeFlag;
use Salient\Contract\Core\QueryFlag;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerMiddlewareInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\AccessTokenInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Http\HttpRequestHandlerInterface;
use Salient\Core\AbstractBuilder;
use Closure;
use Stringable;

/**
 * A fluent Curler2 factory
 *
 * @method $this uri(PsrUriInterface|Stringable|string $value) Set Curler2::$Uri
 * @method $this headers(Arrayable<string,string[]|string>|iterable<string,string[]|string>|null $value) Set Curler2::$Headers
 * @method $this accessToken(?AccessTokenInterface $value) Set Curler2::$AccessToken
 * @method $this accessTokenHeaderName(string $value) Set Curler2::$AccessTokenHeaderName
 * @method $this sensitiveHeaders(string[] $value) Set Curler2::$SensitiveHeaders
 * @method $this mediaType(?string $value) Set Curler2::$MediaType
 * @method $this userAgent(?string $value) Set Curler2::$UserAgent
 * @method $this expectJson(bool $value = true) Set Curler2::$ExpectJson (default: true)
 * @method $this postJson(bool $value = true) Set Curler2::$PostJson (default: true)
 * @method $this dateFormatter(?DateFormatterInterface $value) Set Curler2::$DateFormatter
 * @method $this queryFlags(int-mask-of<QueryFlag::*> $value) Set Curler2::$QueryFlags
 * @method $this jsonDecodeFlags(int-mask-of<JsonDecodeFlag::*> $value) Set Curler2::$JsonDecodeFlags
 * @method $this middleware(array<array{CurlerMiddlewareInterface|HttpRequestHandlerInterface|Closure(RequestInterface $request, Closure $next, CurlerInterface $curler): ResponseInterface,1?:string|null}> $value) Apply middleware with an optional name to the request handler stack
 * @method $this pager(?CurlerPagerInterface $value) Set Curler2::$Pager
 * @method $this alwaysPaginate(bool $value = true) Set Curler2::$AlwaysPaginate (default: false)
 * @method $this cacheStore(?CacheStore $value) Set Curler2::$CacheStore
 * @method $this handleCookies(bool $value = true) Pass $value to `$handleCookies` in Curler2::__construct() (default: false)
 * @method $this cookiesCacheKey(?string $value) Set Curler2::$CookiesCacheKey
 * @method $this cacheResponses(bool $value = true) Set Curler2::$CacheResponses (default: false)
 * @method $this cachePostResponses(bool $value = true) Set Curler2::$CachePostResponses (default: false)
 * @method $this cacheKeyCallback((callable(RequestInterface): (string[]|string))|null $value) Pass $value to `$cacheKeyCallback` in Curler2::__construct()
 * @method $this cacheLifetime(int<-1,max> $value) Seconds before cached responses expire when caching is enabled (0 = cache indefinitely, -1 = do not cache)
 * @method $this refreshCache(bool $value = true) Set Curler2::$RefreshCache (default: false)
 * @method $this timeout(int<0,max>|null $value) Set Curler2::$Timeout
 * @method $this followRedirects(bool $value = true) Set Curler2::$FollowRedirects (default: false)
 * @method $this maxRedirects(int<-1,max>|null $value) Set Curler2::$MaxRedirects
 * @method $this retryAfterTooManyRequests(bool $value = true) Set Curler2::$RetryAfterTooManyRequests (default: false)
 * @method $this retryAfterMaxSeconds(int<0,max> $value) Set Curler2::$RetryAfterMaxSeconds
 * @method $this throwHttpErrors(bool $value = true) Set Curler2::$ThrowHttpErrors (default: true)
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
 *
 * @extends AbstractBuilder<Curler2>
 *
 * @generated
 */
final class Curler2Builder extends AbstractBuilder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return Curler2::class;
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
        ];
    }
}
