<?php declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Support\DateFormatter;

/**
 * A fluent interface for creating Curler objects
 *
 * @method static $this build(?IContainer $container = null) Create a new CurlerBuilder (syntactic sugar for 'new CurlerBuilder()')
 * @method $this baseUrl(string $value) Request endpoint
 * @method $this headers(?ICurlerHeaders $value) Request headers
 * @method $this pager(?ICurlerPager $value) Pagination handler
 * @method $this cacheResponse(bool $value = true) Cache responses to GET and HEAD requests? (HTTP caching directives are ignored) (default: false; see {@see Curler::$CacheResponse})
 * @method $this cachePostResponse(bool $value = true) Cache responses to eligible POST requests? (default: false; see {@see Curler::$CachePostResponse})
 * @method $this expiry(int $value) Seconds before cached responses expire (0 = no expiry) (see {@see Curler::$Expiry})
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
 * @method $this dateFormatter(?DateFormatter $value) Specify the date format and timezone used upstream
 * @method $this userAgent(?string $value) Override the default User-Agent header (see {@see Curler::$UserAgent})
 * @method $this alwaysPaginate(bool $value = true) Pass every response to the pager? (default: false)
 * @method $this objectAsArray(bool $value = true) Return deserialized objects as associative arrays? (default: true)
 * @method mixed get(string $name) The value of $name if applied to the unresolved Curler by calling $name(), otherwise null
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved Curler by calling $name()
 * @method Curler go() Get a new Curler object
 * @method static Curler resolve(Curler|CurlerBuilder $object) Resolve a CurlerBuilder or Curler object to a Curler object
 *
 * @uses Curler
 *
 * @extends Builder<Curler>
 */
final class CurlerBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return Curler::class;
    }
}
