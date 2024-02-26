<?php declare(strict_types=1);

namespace Salient\Curler;

use Salient\Core\Contract\DateFormatterInterface;
use Salient\Core\AbstractBuilder;
use Salient\Curler\Catalog\CurlerProperty;
use Salient\Curler\Contract\ICurlerPager;
use Salient\Http\Contract\HttpHeadersInterface;

/**
 * A fluent Curler factory
 *
 * @method $this baseUrl(string $value) Resource URL (no query or fragment)
 * @method $this headers(?HttpHeadersInterface $value) Request headers
 * @method $this pager(?ICurlerPager $value) Pagination handler
 * @method $this cacheResponse(bool $value = true) Cache responses to GET and HEAD requests? (ignored if $expiry is null or >= 0; HTTP caching directives are not honoured) (default: false; see {@see Curler::$CacheResponse})
 * @method $this cachePostResponse(bool $value = true) Cache responses to eligible POST requests? (default: false; see {@see Curler::$CachePostResponse})
 * @method $this expiry(int|null $value) Seconds before cached responses expire (0 = no expiry, null = do not cache, -1 = use default expiry) (see {@see Curler::$Expiry})
 * @method $this flush(bool $value = true) Replace cached responses that haven't expired? (default: false; see {@see Curler::$Flush})
 * @method $this responseCacheKeyCallback((callable(Curler): string[])|null $value) Override the default cache key when saving and loading cached responses (see {@see Curler::$ResponseCacheKeyCallback})
 * @method $this throwHttpErrors(bool $value = true) Throw an exception if the status code is >= 400? (default: true)
 * @method $this responseCallback((callable(Curler): Curler)|null $value) Apply a callback to responses before they are returned (see {@see Curler::$ResponseCallback})
 * @method $this connectTimeout(?int $value) Override the default number of seconds the connection phase of the transfer is allowed to take (see {@see Curler::$ConnectTimeout})
 * @method $this timeout(?int $value) Limit the number of seconds the transfer is allowed to take
 * @method $this followRedirects(bool $value = true) Follow "Location:" headers? (default: false; see {@see Curler::$FollowRedirects})
 * @method $this maxRedirects(?int $value) Limit the number of redirections followed when FollowRedirects is set
 * @method $this handleCookies(bool $value = true) Send and receive cookies? (default: false; see {@see Curler::$HandleCookies})
 * @method $this cookieCacheKey(?string $value) Override the default cache key when saving and loading cookies
 * @method $this retryAfterTooManyRequests(bool $value = true) Retry after receiving "429 Too Many Requests" with a Retry-After header? (default: false; see {@see Curler::$RetryAfterTooManyRequests})
 * @method $this retryAfterMaxSeconds(int $value) Limit the delay between attempts when RetryAfterTooManyRequests is set (see {@see Curler::$RetryAfterMaxSeconds})
 * @method $this expectJson(bool $value = true) Request JSON from upstream? (default: true)
 * @method $this postJson(bool $value = true) Use JSON to serialize POST/PUT/PATCH/DELETE data? (default: true)
 * @method $this preserveKeys(bool $value = true) Suppress removal of numeric indices from serialized lists? (default: false)
 * @method $this dateFormatter(?DateFormatterInterface $value) Specify the date format and timezone used upstream
 * @method $this userAgent(?string $value) Override the default User-Agent header (see {@see Curler::$UserAgent})
 * @method $this alwaysPaginate(bool $value = true) Pass every response to the pager? (default: false)
 * @method $this objectAsArray(bool $value = true) Return deserialized objects as associative arrays? (default: true)
 * @method Curler addHeader(string $name, string[]|string $value) Call Curler::addHeader() on a new instance
 * @method Curler unsetHeader(string $name) Call Curler::unsetHeader() on a new instance
 * @method Curler setHeader(string $name, string[]|string $value) Call Curler::setHeader() on a new instance
 * @method Curler addSensitiveHeaderName(string $name) Call Curler::addSensitiveHeaderName() on a new instance
 * @method Curler setContentType(?string $mimeType) Call Curler::setContentType() on a new instance
 * @method Curler with(string&CurlerProperty::* $property, mixed $value) Apply a value to a clone of the instance
 * @method HttpHeadersInterface getPublicHeaders() Get request headers that are not considered sensitive
 * @method Curler flushCookies() Call Curler::flushCookies() on a new instance
 * @method HttpHeadersInterface head(mixed[]|null $query = null) Call Curler::head() on a new instance
 * @method mixed get(mixed[]|null $query = null) Call Curler::get() on a new instance
 * @method mixed post(mixed[]|object|null $data = null, mixed[]|null $query = null) Call Curler::post() on a new instance
 * @method mixed put(mixed[]|object|null $data = null, mixed[]|null $query = null) Call Curler::put() on a new instance
 * @method mixed patch(mixed[]|object|null $data = null, mixed[]|null $query = null) Call Curler::patch() on a new instance
 * @method mixed delete(mixed[]|object|null $data = null, mixed[]|null $query = null) Call Curler::delete() on a new instance
 * @method iterable<mixed> getP(mixed[]|null $query = null) Call Curler::getP() on a new instance
 * @method iterable<mixed> postP(mixed[]|object|null $data = null, mixed[]|null $query = null) Call Curler::postP() on a new instance
 * @method iterable<mixed> putP(mixed[]|object|null $data = null, mixed[]|null $query = null) Call Curler::putP() on a new instance
 * @method iterable<mixed> patchP(mixed[]|object|null $data = null, mixed[]|null $query = null) Call Curler::patchP() on a new instance
 * @method iterable<mixed> deleteP(mixed[]|object|null $data = null, mixed[]|null $query = null) Call Curler::deleteP() on a new instance
 * @method mixed rawPost(string $data, string $mimeType, mixed[]|null $query = null) Call Curler::rawPost() on a new instance
 * @method mixed rawPut(string $data, string $mimeType, mixed[]|null $query = null) Call Curler::rawPut() on a new instance
 * @method mixed rawPatch(string $data, string $mimeType, mixed[]|null $query = null) Call Curler::rawPatch() on a new instance
 * @method mixed rawDelete(string $data, string $mimeType, mixed[]|null $query = null) Call Curler::rawDelete() on a new instance
 * @method mixed[] getAllLinked(mixed[] $query = null) Follow HTTP `Link` headers to retrieve and merge paged JSON data (see {@see Curler::getAllLinked()})
 * @method mixed[] getAllLinkedByEntity(string $entityName, mixed[] $query = null) Follow `$result['links']['next']` to retrieve and merge paged JSON data (see {@see Curler::getAllLinkedByEntity()})
 * @method mixed[] getByGraphQL(string $query, array<string,mixed>|null $variables = null, ?string $entityPath = null, ?string $pagePath = null, ?callable $filter = null, ?int $requestLimit = null) Call Curler::getByGraphQL() on a new instance
 *
 * @extends AbstractBuilder<Curler>
 *
 * @generated
 */
final class CurlerBuilder extends AbstractBuilder
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
    protected static function getTerminators(): array
    {
        return [
            'addHeader',
            'unsetHeader',
            'setHeader',
            'addSensitiveHeaderName',
            'setContentType',
            'with',
            'getPublicHeaders',
            'flushCookies',
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
            'rawPost',
            'rawPut',
            'rawPatch',
            'rawDelete',
            'getAllLinked',
            'getAllLinkedByEntity',
            'getByGraphQL',
        ];
    }
}
