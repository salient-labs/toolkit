<?php declare(strict_types=1);

namespace Lkrms\Curler;

use DateTimeInterface;
use Lkrms\Concern\TReadable;
use Lkrms\Concern\TWritable;
use Lkrms\Contract\HasBuilder;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IWritable;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Exception\CurlerException;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\Dictionary\HttpHeader;
use Lkrms\Support\Dictionary\HttpRequestMethod;
use Lkrms\Support\Dictionary\MimeType;
use Lkrms\Support\Iterator\RecursiveHasChildrenCallbackIterator;
use Lkrms\Support\Iterator\RecursiveObjectOrArrayIterator;
use Lkrms\Utility\Test;
use RecursiveIteratorIterator;
use UnexpectedValueException;

/**
 * A cURL wrapper optimised for consumption of REST APIs
 *
 * @property-read string $BaseUrl Request URL
 * @property-read ICurlerHeaders $Headers Request headers
 * @property-read string|null $Method Last request method
 * @property-read string|null $QueryString Query string last added to request URL
 * @property-read string|mixed[]|null $Body Request body, as passed to cURL
 * @property-read string|mixed[]|object|null $Data Request body, before serialization
 * @property-read ICurlerPager|null $Pager Pagination handler
 * @property-read ICurlerHeaders|null $ResponseHeaders Response headers
 * @property-read array<string,string>|null $ResponseHeadersByName An array that maps lowercase response headers to their combined values
 * @property-read int|null $StatusCode Response status code
 * @property-read string|null $ReasonPhrase Response status explanation
 * @property-read string|null $ResponseBody Response body
 * @property-read mixed[]|null $CurlInfo curl_getinfo()'s last return value
 * @property bool $CacheResponse Cache responses to GET and HEAD requests?
 * @property bool $CachePostResponse Cache responses to eligible POST requests?
 * @property int $Expiry Seconds before cached responses expire
 * @property bool $Flush Replace cached responses that haven't expired?
 * @property callable|null $ResponseCacheKeyCallback Override the default cache key when saving and loading cached responses
 * @property bool $ThrowHttpErrors Throw an exception if the status code is >= 400?
 * @property bool $FollowRedirects Follow "Location:" headers?
 * @property int|null $MaxRedirects Limit the number of redirections followed when FollowRedirects is set
 * @property bool $HandleCookies Send and receive cookies?
 * @property string|null $CookieCacheKey Override the default cache key when saving and loading cookies
 * @property bool $RetryAfterTooManyRequests Retry after receiving "429 Too Many Requests" with a Retry-After header?
 * @property int $RetryAfterMaxSeconds Limit the delay between attempts when RetryAfterTooManyRequests is set
 * @property bool $ExpectJson Request JSON from upstream?
 * @property bool $PostJson Use JSON to serialize POST/PUT/PATCH/DELETE data?
 * @property bool $PreserveKeys Suppress removal of numeric indices from serialized lists?
 * @property DateFormatter|null $DateFormatter Specify the date format and timezone used upstream
 * @property string|null $UserAgent Override the default User-Agent header
 * @property bool $AlwaysPaginate Pass every response to the pager?
 * @property bool $ObjectAsArray Return deserialized objects as associative arrays?
 */
final class Curler implements IReadable, IWritable, HasBuilder
{
    use TReadable, TWritable;

    /**
     * Request URL
     *
     * @var string
     */
    protected $BaseUrl;

    /**
     * Request headers
     *
     * @var ICurlerHeaders
     */
    protected $Headers;

    /**
     * Last request method
     *
     * @var string|null
     */
    protected $Method;

    /**
     * Query string last added to request URL
     *
     * @var string|null
     */
    protected $QueryString;

    /**
     * Request body, as passed to cURL
     *
     * @var string|mixed[]|null
     */
    protected $Body;

    /**
     * Request body, before serialization
     *
     * @var string|mixed[]|object|null
     */
    protected $Data;

    /**
     * Pagination handler
     *
     * @var ICurlerPager|null
     */
    protected $Pager;

    /**
     * Response headers
     *
     * @var ICurlerHeaders|null
     */
    protected $ResponseHeaders;

    /**
     * An array that maps lowercase response headers to their combined values
     *
     * @var array<string,string>|null
     */
    protected $ResponseHeadersByName;

    /**
     * Response status code
     *
     * @var int|null
     */
    protected $StatusCode;

    /**
     * Response status explanation
     *
     * @var string|null
     */
    protected $ReasonPhrase;

    /**
     * Response body
     *
     * @var string|null
     */
    protected $ResponseBody;

    /**
     * curl_getinfo()'s last return value
     *
     * Set by {@see Curler::withCurlInfo()}.
     *
     * @var mixed[]|null
     */
    protected $CurlInfo;

    /**
     * Cache responses to GET and HEAD requests?
     *
     * @var bool
     */
    protected $CacheResponse = false;

    /**
     * Cache responses to eligible POST requests?
     *
     * Ignored unless {@see Curler::$CacheResponse} is `true`.
     *
     * @var bool
     */
    protected $CachePostResponse = false;

    /**
     * Seconds before cached responses expire
     *
     * `0` = no expiry.
     *
     * Ignored unless {@see Curler::$CacheResponse} is `true`.
     *
     * @var int
     */
    protected $Expiry = 3600;

    /**
     * Replace cached responses that haven't expired?
     *
     * @var bool
     */
    protected $Flush = false;

    /**
     * Override the default cache key when saving and loading cached responses
     *
     * The `string[]` returned by the callback is hashed and combined with the
     * request method and effective URL.
     *
     * The default callback returns unprivileged request headers from
     * {@see ICurlerHeaders::getPublicHeaders()}.
     *
     * Ignored unless {@see Curler::$CacheResponse} is `true`.
     *
     * @var (callable(Curler): string[])|null
     */
    protected $ResponseCacheKeyCallback;

    /**
     * Throw an exception if the status code is >= 400?
     *
     * @var bool
     */
    protected $ThrowHttpErrors = true;

    /**
     * Follow "Location:" headers?
     *
     * Use {@see Curler::$MaxRedirects} to limit the number of redirections.
     * PHP's default is 20.
     *
     * @var bool
     */
    protected $FollowRedirects = false;

    /**
     * Limit the number of redirections followed when FollowRedirects is set
     *
     * @var int|null
     */
    protected $MaxRedirects;

    /**
     * Send and receive cookies?
     *
     * If set, the shared {@see \Lkrms\Store\CacheStore} instance serviced by
     * {@see \Lkrms\Facade\Cache} will be used for cookie storage.
     *
     * @var bool
     */
    protected $HandleCookies = false;

    /**
     * Override the default cache key when saving and loading cookies
     *
     * @var string|null
     */
    protected $CookieCacheKey;

    /**
     * Retry after receiving "429 Too Many Requests" with a Retry-After header?
     *
     * Use {@see Curler::$RetryAfterMaxSeconds} to limit the delay between
     * attempts.
     *
     * @var bool
     */
    protected $RetryAfterTooManyRequests = false;

    /**
     * Limit the delay between attempts when RetryAfterTooManyRequests is set
     *
     * If upstream's `Retry-After` exceeds this value, the request is not
     * retried and "429 Too Many Requests" is handled like any other HTTP error.
     *
     * `0` = unlimited delay between attempts.
     *
     * @var int
     */
    protected $RetryAfterMaxSeconds = 60;

    /**
     * Request JSON from upstream?
     *
     * @var bool
     */
    protected $ExpectJson = true;

    /**
     * Use JSON to serialize POST/PUT/PATCH/DELETE data?
     *
     * @var bool
     */
    protected $PostJson = true;

    /**
     * Suppress removal of numeric indices from serialized lists?
     *
     * @var bool
     */
    protected $PreserveKeys = false;

    /**
     * Specify the date format and timezone used upstream
     *
     * @var DateFormatter|null
     */
    protected $DateFormatter;

    /**
     * Override the default User-Agent header
     *
     * @var string|null
     */
    protected $UserAgent;

    /**
     * Pass every response to the pager?
     *
     * @var bool
     */
    protected $AlwaysPaginate = false;

    /**
     * Return deserialized objects as associative arrays?
     *
     * @var bool
     */
    protected $ObjectAsArray = true;

    /**
     * @var \CurlHandle|resource|null
     */
    private $Handle;

    /**
     * @var \CurlMultiHandle|resource|null
     */
    private $MultiHandle;

    /**
     * @var array[]
     */
    private $MultiInfo = [];

    /**
     * @var int
     */
    private $ExecuteCount = 0;

    /**
     * @var string|null
     */
    private $CookieKey;

    /**
     * @var string|null
     */
    private static $DefaultUserAgent;

    /**
     * @param (callable(Curler): string[])|null $responseCacheKeyCallback
     */
    public function __construct(
        string $baseUrl,
        ?ICurlerHeaders $headers = null,
        ?ICurlerPager $pager = null,
        bool $cacheResponse = false,
        bool $cachePostResponse = false,
        int $expiry = 3600,
        bool $flush = false,
        ?callable $responseCacheKeyCallback = null,
        bool $throwHttpErrors = true,
        bool $followRedirects = false,
        ?int $maxRedirects = null,
        bool $handleCookies = false,
        ?string $cookieCacheKey = null,
        bool $retryAfterTooManyRequests = false,
        int $retryAfterMaxSeconds = 60,
        bool $expectJson = true,
        bool $postJson = true,
        bool $preserveKeys = false,
        ?DateFormatter $dateFormatter = null,
        ?string $userAgent = null,
        bool $alwaysPaginate = false,
        bool $objectAsArray = true
    ) {
        $this->BaseUrl = $baseUrl;
        $this->Headers = $headers ?: new CurlerHeaders();
        $this->Pager = $pager;
        $this->CacheResponse = $cacheResponse;
        $this->CachePostResponse = $cachePostResponse;
        $this->Expiry = $expiry;
        $this->Flush = $flush;
        $this->ResponseCacheKeyCallback = $responseCacheKeyCallback;
        $this->ThrowHttpErrors = $throwHttpErrors;
        $this->FollowRedirects = $followRedirects;
        $this->MaxRedirects = $maxRedirects;
        $this->HandleCookies = $handleCookies;
        $this->CookieCacheKey = $cookieCacheKey;
        $this->RetryAfterTooManyRequests = $retryAfterTooManyRequests;
        $this->RetryAfterMaxSeconds = $retryAfterMaxSeconds;
        $this->ExpectJson = $expectJson;
        $this->PostJson = $postJson;
        $this->PreserveKeys = $preserveKeys;
        $this->DateFormatter = $dateFormatter;
        $this->UserAgent = $userAgent;
        $this->AlwaysPaginate = $alwaysPaginate;
        $this->ObjectAsArray = $objectAsArray;
    }

    /**
     * @return $this
     */
    final public function addHeader(string $name, string $value, bool $private = false)
    {
        $this->Headers = $this->Headers->addHeader($name, $value, $private);

        return $this;
    }

    /**
     * @return $this
     */
    final public function unsetHeader(string $name, ?string $pattern = null)
    {
        $this->Headers = $this->Headers->unsetHeader($name, $pattern);

        return $this;
    }

    /**
     * @return $this
     */
    final public function setHeader(string $name, string $value, bool $private = false)
    {
        $this->Headers = $this->Headers->setHeader($name, $value, $private);

        return $this;
    }

    /**
     * @return $this
     */
    final public function addPrivateHeaderName(string $name)
    {
        $this->Headers = $this->Headers->addPrivateHeaderName($name);

        return $this;
    }

    /**
     * @return $this
     */
    final public function setContentType(?string $mimeType)
    {
        $this->Headers =
            is_null($mimeType)
                ? $this->Headers->unsetHeader(HttpHeader::CONTENT_TYPE)
                : $this->Headers->setHeader(HttpHeader::CONTENT_TYPE, $mimeType);

        return $this;
    }

    /**
     * Apply new headers to a clone of the instance
     *
     * @return $this
     */
    final public function withHeaders(ICurlerHeaders $headers)
    {
        $clone = clone $this;
        $clone->Headers = $headers;

        return $clone;
    }

    /**
     * Apply a new pager to a clone of the instance
     *
     * @return $this
     */
    final public function withPager(ICurlerPager $pager)
    {
        $clone = clone $this;
        $clone->Pager = $pager;

        return $clone;
    }

    /**
     * @return $this
     */
    final public function flushCookies()
    {
        if ($cookieKey = $this->getCookieKey()) {
            Cache::delete($cookieKey);
        }

        return $this;
    }

    final public function responseContentTypeIs(string $mimeType): bool
    {
        $contentType = $this->ResponseHeaders->getHeaderValue(
            HttpHeader::CONTENT_TYPE,
            CurlerHeadersFlag::KEEP_LAST
        );

        // Assume JSON if it's expected and no Content-Type is specified
        if (is_null($contentType)) {
            return !strcasecmp($mimeType, MimeType::JSON) && $this->ExpectJson;
        }

        return MimeType::is($mimeType, $contentType);
    }

    /**
     * Create a clone of the instance with CurlInfo set
     *
     * @return $this
     */
    final public function withCurlInfo()
    {
        $clone = clone $this;
        if ($clone->Handle) {
            $clone->CurlInfo = $clone->CurlInfo ?: curl_getinfo($clone->Handle);
        }

        return $clone;
    }

    final protected function getEffectiveUrl(): ?string
    {
        return $this->Handle
            ? curl_getinfo($this->Handle, CURLINFO_EFFECTIVE_URL)
            : null;
    }

    final protected function close(): void
    {
        if (is_null($this->Handle)) {
            return;
        }

        if ($this->CookieKey && $this->ExecuteCount) {
            Cache::set($this->CookieKey, curl_getinfo($this->Handle, CURLINFO_COOKIELIST));
        }

        curl_close($this->Handle);
        $this->Handle = null;
    }

    private function initialise(string $method, ?array $query, ?ICurlerPager $pager = null): void
    {
        if ($pager) {
            $query = $pager->prepareQuery($query);
        }
        $this->QueryString = $this->getQueryString($query);

        $this->createHandle($this->BaseUrl . $this->QueryString);

        if ($method === HttpRequestMethod::GET) {
            $this->Method = $method;
        } else {
            $this->applyMethod($method);
        }

        $this->clearResponse();
        $this->setContentType(null);
        $this->Body = $this->Data = null;

        if ($pager) {
            $pager->prepareCurler($this);
        }

        if ($this->ExpectJson) {
            $this->Headers = $this->Headers->setHeader(
                HttpHeader::ACCEPT,
                MimeType::JSON
            );
        }

        if (!$this->Headers->hasHeader(HttpHeader::USER_AGENT)) {
            $this->Headers = $this->Headers->setHeader(
                HttpHeader::USER_AGENT,
                $this->UserAgent ?: self::getDefaultUserAgent()
            );
        }
    }

    final public function getQueryUrl(?array $query): string
    {
        return $this->BaseUrl . $this->getQueryString($query);
    }

    private function getQueryString(?array $query): string
    {
        if (!$query) {
            return '';
        }

        return '?' . Convert::dataToQuery(
            $query,
            $this->PreserveKeys,
            $this->DateFormatter
        );
    }

    private function createHandle(string $url): void
    {
        $this->ExecuteCount = 0;

        $this->Handle = curl_init($url);

        // Return the transfer as a string
        curl_setopt($this->Handle, CURLOPT_RETURNTRANSFER, true);

        // Enable all supported encoding types (e.g. gzip, deflate) and set
        // Accept-Encoding header accordingly
        curl_setopt($this->Handle, CURLOPT_ENCODING, '');

        // Collect response headers
        curl_setopt(
            $this->Handle,
            CURLOPT_HEADERFUNCTION,
            fn($curl, $header) => strlen($this->processHeader($header))
        );

        if ($this->FollowRedirects) {
            curl_setopt($this->Handle, CURLOPT_FOLLOWLOCATION, true);
            if (!is_null($this->MaxRedirects)) {
                curl_setopt($this->Handle, CURLOPT_MAXREDIRS, $this->MaxRedirects);
            }
        }

        if ($this->CookieKey = $this->getCookieKey()) {
            // Enable cookies without loading them from a file
            curl_setopt($this->Handle, CURLOPT_COOKIEFILE, '');

            foreach (Cache::get($this->CookieKey) ?: [] as $cookie) {
                curl_setopt($this->Handle, CURLOPT_COOKIELIST, $cookie);
            }
        }

        // In debug mode, collect request headers
        if (Env::debug()) {
            curl_setopt($this->Handle, CURLINFO_HEADER_OUT, true);
        }
    }

    private function processHeader(string $header): string
    {
        if (!is_null($this->ReasonPhrase)) {
            $this->ResponseHeaders = $this->ResponseHeaders->addRawHeader($header);
        } elseif (count($split = explode(' ', $header, 3)) > 1 && explode('/', $split[0])[0] === 'HTTP') {
            $this->ReasonPhrase = trim($split[2] ?? '');
        } else {
            throw new CurlerException($this, 'Invalid status line in response');
        }

        return $header;
    }

    private function applyMethod(string $method): void
    {
        switch ($method) {
            case HttpRequestMethod::GET:
                curl_setopt($this->Handle, CURLOPT_HTTPGET, true);
                break;

            case HttpRequestMethod::POST:
                curl_setopt($this->Handle, CURLOPT_POST, true);
                break;

            case HttpRequestMethod::HEAD:
            case HttpRequestMethod::PUT:
            case HttpRequestMethod::PATCH:
            case HttpRequestMethod::DELETE:
            case HttpRequestMethod::CONNECT:
            case HttpRequestMethod::OPTIONS:
            case HttpRequestMethod::TRACE:
                curl_setopt($this->Handle, CURLOPT_CUSTOMREQUEST, $method);
                break;

            default:
                throw new UnexpectedValueException("Invalid HTTP request method: $method");
        }

        $this->Method = $method;
    }

    private function clearResponse(): void
    {
        $this->ResponseHeaders = new CurlerHeaders();
        $this->ResponseHeadersByName = null;
        $this->StatusCode = null;
        $this->ReasonPhrase = null;
        $this->ResponseBody = null;
        $this->CurlInfo = null;
    }

    /**
     * @param mixed[]|object $data
     */
    private function applyData($data): void
    {
        curl_setopt(
            $this->Handle,
            CURLOPT_POSTFIELDS,
            ($this->Body = $this->prepareData($data))
        );
    }

    /**
     * @param mixed[]|object $data
     * @return string|mixed[]
     */
    private function prepareData($data)
    {
        // Iterate over `$data` recursively
        $iterator = new RecursiveObjectOrArrayIterator($data);
        // Treat `CurlerFile` and `DateTimeInterface` instances as leaf nodes
        $iterator = new RecursiveHasChildrenCallbackIterator(
            $iterator,
            fn($value) =>
                !($value instanceof CurlerFile ||
                    $value instanceof DateTimeInterface)
        );
        $leafIterator = new RecursiveIteratorIterator($iterator);

        // Does `$data` contain a `CurlerFile`?
        $file = false;
        foreach ($leafIterator as $value) {
            if ($value instanceof CurlerFile) {
                $file = true;
                break;
            }
        }

        // With that answered, start over, replacing `CurlerFile` and
        // `DateTimeInterface` instances
        /** @var RecursiveObjectOrArrayIterator $iterator */
        foreach ($iterator as $value) {
            if ($value instanceof CurlerFile) {
                $value = $value->getCurlFile();
            } elseif ($value instanceof DateTimeInterface) {
                $value = $this->getDateFormatter()->format($value);
            } elseif ($file) {
                // And if uploading a file, replace every object that isn't a
                // CURLFile with an array cURL can encode
                $iterator->maybeReplaceCurrentWithArray();
                continue;
            } else {
                continue;
            }
            $iterator->replace($value);
        }

        if ($file) {
            return $data;
        }

        if ($this->PostJson) {
            $this->setContentType(MimeType::JSON);

            return json_encode($data);
        }

        $this->setContentType(MimeType::WWW_FORM);

        return Convert::dataToQuery(
            $data,
            $this->PreserveKeys,
            $this->DateFormatter
        );
    }

    private function getCookieKey(): ?string
    {
        return $this->HandleCookies
            ? Convert::sparseToString(':', [self::class, 'cookies', $this->CookieCacheKey])
            : null;
    }

    private function getDateFormatter(): DateFormatter
    {
        return $this->DateFormatter
            ?: ($this->DateFormatter = new DateFormatter());
    }

    private static function getDefaultUserAgent(): string
    {
        return self::$DefaultUserAgent
            ?: (self::$DefaultUserAgent = implode(' ', [
                str_replace('/', '~', Composer::getRootPackageName())
                    . '/' . Composer::getRootPackageVersion(true, true),
                'php/' . PHP_VERSION
            ]));
    }

    protected function execute(bool $close = true, int $depth = 0): string
    {
        if ($this->CacheResponse &&
                ($cacheKey = $this->getCacheKey()) &&
                !$this->Flush &&
                ($last = Cache::get($cacheKey, $this->Expiry)) !== false) {
            if ($close) {
                $this->close();
            }

            $this->StatusCode = $last[0];
            $this->ReasonPhrase = $last[1];
            $this->ResponseHeaders = $last[2];
            $this->ResponseBody = $last[3];

            $this->ResponseHeadersByName =
                $this->ResponseHeaders
                     ->getHeaderValues(CurlerHeadersFlag::COMBINE);

            return $this->ResponseBody;
        }

        $this->ExecuteCount++;

        curl_setopt($this->Handle, CURLOPT_HTTPHEADER, $this->Headers->getHeaders());

        // Use a cURL multi handle for upcoming simultaneous request handling
        if (is_null($this->MultiHandle)) {
            $this->MultiHandle = curl_multi_init();
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            if (Env::debug() || $this->Method != HttpRequestMethod::GET) {
                // Console::debug() should print the details of whatever called
                // one of Curler's public methods, i.e. not execute(), not
                // get(), but one frame deeper
                Console::debug(
                    "{$this->Method} "
                        . rawurldecode(curl_getinfo($this->Handle, CURLINFO_EFFECTIVE_URL)),
                    null,
                    null,
                    $depth + 3
                );
            }

            // Execute the request
            curl_multi_add_handle($this->MultiHandle, $this->Handle);
            $active = null;
            $error = null;

            do {
                if (($status = curl_multi_exec($this->MultiHandle, $active)) !== CURLM_OK) {
                    throw new CurlerException($this, 'cURL error: ' . curl_multi_strerror($status));
                }

                if ($active) {
                    if (curl_multi_select($this->MultiHandle) === -1) {
                        // 100 milliseconds, as suggested here:
                        // https://curl.se/libcurl/c/curl_multi_fdset.html
                        usleep(100000);
                    }
                }

                while (($message = curl_multi_info_read($this->MultiHandle)) !== false) {
                    $this->MultiInfo[] = $message;
                }
            } while ($active);

            // Claim messages related to this request
            foreach ($this->MultiInfo as $i => $message) {
                if ($message['handle'] === $this->Handle) {
                    if ($message['result'] !== CURLE_OK) {
                        $error = $message['result'];
                    }

                    unset($this->MultiInfo[$i]);
                }
            }

            curl_multi_remove_handle($this->MultiHandle, $this->Handle);

            if (is_null($error)) {
                // ReasonPhrase is collected by processHeader()
                $this->ResponseHeadersByName = $this->ResponseHeaders->getHeaderValues(CurlerHeadersFlag::COMBINE);
                $this->StatusCode = (int) curl_getinfo($this->Handle, CURLINFO_RESPONSE_CODE);
                $this->ResponseBody = curl_multi_getcontent($this->Handle);

                if (Env::debug()) {
                    $this->CurlInfo = curl_getinfo($this->Handle);
                }
            } else {
                throw new CurlerException($this, 'cURL error: ' . curl_strerror($error));
            }

            if ($this->StatusCode === 429 &&
                    $this->RetryAfterTooManyRequests &&
                    $attempt === 0 &&
                    !is_null($after = $this->getRetryAfter()) &&
                    ($this->RetryAfterMaxSeconds === 0 || $after <= $this->RetryAfterMaxSeconds)) {
                // Sleep for at least one second
                $after = max(1, $after);
                Console::debug(
                    "Received HTTP error 429 Too Many Requests, sleeping for {$after}s",
                    null,
                    null,
                    $depth + 3
                );
                sleep($after);

                $this->clearResponse();
                continue;
            }

            break;
        }

        if ($this->StatusCode >= 400 && $this->ThrowHttpErrors) {
            throw new CurlerException($this, sprintf('HTTP error %d %s', $this->StatusCode, $this->ReasonPhrase));
        }

        if ($close) {
            $this->close();
        }

        if ($cacheKey ?? null) {
            Cache::set(
                $cacheKey,
                [$this->StatusCode, $this->ReasonPhrase, $this->ResponseHeaders, $this->ResponseBody],
                $this->Expiry
            );
        }

        return $this->ResponseBody;
    }

    private function getCacheKey(): ?string
    {
        if (!($this->Method === HttpRequestMethod::GET ||
            $this->Method === HttpRequestMethod::HEAD ||
            ($this->Method === HttpRequestMethod::POST &&
                $this->CachePostResponse &&
                !is_array($this->Body))) ||
            !($url = $this->getEffectiveUrl()
                ?: $this->BaseUrl . $this->QueryString)) {
            return null;
        }

        $key = $this->ResponseCacheKeyCallback
            ? ($this->ResponseCacheKeyCallback)($this)
            : $this->Headers->getPublicHeaders();
        if ($this->Method === HttpRequestMethod::POST) {
            $key[] = $this->Body;
        }

        return implode(':', [
            self::class,
            'response',
            $this->Method,
            rawurlencode($url),
            Compute::hash(...$key),
        ]);
    }

    /**
     *
     * @param mixed[]|null $query
     */
    final public function head(?array $query = null): ICurlerHeaders
    {
        return $this->process(HttpRequestMethod::HEAD, $query);
    }

    /**
     *
     * @param mixed[]|null $query
     * @return mixed
     */
    final public function get(?array $query = null)
    {
        return $this->process(HttpRequestMethod::GET, $query);
    }

    /**
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    final public function post($data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::POST, $query, $data);
    }

    /**
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    final public function put($data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PUT, $query, $data);
    }

    /**
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    final public function patch($data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PATCH, $query, $data);
    }

    /**
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    final public function delete($data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::DELETE, $query, $data);
    }

    /**
     *
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    final public function getP(?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::GET, $query);
    }

    /**
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    final public function postP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::POST, $query, $data);
    }

    /**
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    final public function putP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::PUT, $query, $data);
    }

    /**
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    final public function patchP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::PATCH, $query, $data);
    }

    /**
     *
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    final public function deleteP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::DELETE, $query, $data);
    }

    final public function rawPost(string $data, string $mimeType, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::POST, $query, $data, $mimeType);
    }

    final public function rawPut(string $data, string $mimeType, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PUT, $query, $data, $mimeType);
    }

    final public function rawPatch(string $data, string $mimeType, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PATCH, $query, $data, $mimeType);
    }

    final public function rawDelete(string $data, string $mimeType, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::DELETE, $query, $data, $mimeType);
    }

    /**
     * @param mixed[]|null $query
     * @param string|mixed[]|object|null $data
     * @return mixed
     */
    private function process(string $method, ?array $query, $data = null, ?string $mimeType = null)
    {
        $isRaw = !is_null($mimeType);
        if ($this->Pager && $this->AlwaysPaginate && !$isRaw) {
            $pager = $this->Pager;
            if ($method !== HttpRequestMethod::GET) {
                $data = $pager->prepareData($data);
            }
        }

        $this->initialise($method, $query, $pager ?? null);

        $this->Data = $data;
        if (is_array($data) || is_object($data)) {
            $this->applyData($data);
        } elseif (is_string($data) && $mimeType) {
            curl_setopt($this->Handle, CURLOPT_POSTFIELDS, $data);
            $this->setContentType($mimeType);
        }

        $this->execute();

        if ($method === HttpRequestMethod::HEAD) {
            return $this->ResponseHeaders;
        }

        if ($this->responseContentTypeIs(MimeType::JSON)) {
            $response = json_decode($this->ResponseBody, $this->ObjectAsArray);

            if ($pager ?? null) {
                $response = $pager->getPage($response, $this)->entities();
                if (array_keys($response) === [0] &&
                        (is_array($response[0]) || is_scalar($response[0]))) {
                    return $response[0];
                }
            }

            return $response;
        }

        return $this->ResponseBody ?: '';
    }

    /**
     * @param mixed[]|null $query
     * @param mixed[]|object|null $data
     * @return iterable<mixed>
     */
    private function paginate(string $method, ?array $query, $data = null): iterable
    {
        if (!$this->Pager) {
            throw new UnexpectedValueException(static::class . '::$Pager is not set');
        }
        if ($method !== HttpRequestMethod::GET) {
            $data = $this->Pager->prepareData($data);
        }

        $this->initialise($method, $query, $this->Pager);

        do {
            if (!is_null($data)) {
                $this->Data = $data;
                $this->applyData($data);
            }

            $this->execute(false);

            if ($this->responseContentTypeIs(MimeType::JSON)) {
                $response = json_decode($this->ResponseBody, $this->ObjectAsArray);
            } else {
                throw new CurlerException($this, 'Unable to deserialize response');
            }

            $page = $this->Pager->getPage($response, $this, $page ?? null);

            foreach ($page->entities() as $entity) {
                yield $entity;
            }

            if ($page->isLastPage()) {
                break;
            }
            [$url, $data, $headers] =
                [$page->nextUrl(), $page->nextData(), $page->nextHeaders()];
            curl_setopt($this->Handle, CURLOPT_URL, $url);
            if (!is_null($headers)) {
                $this->Headers = $headers;
            }
            $this->clearResponse();
        } while (true);

        $this->close();
    }

    private function getRetryAfter(): ?int
    {
        if (preg_match('/^[0-9]+$/', $retryAfter = $this->ResponseHeadersByName['retry-after'] ?? null)) {
            return (int) $retryAfter;
        }
        if (($retryAfter = strtotime($retryAfter)) !== false) {
            return max(0, $retryAfter - time());
        }

        return null;
    }

    /**
     * @internal
     */
    public static function getReadable(): array
    {
        return [
            'BaseUrl',
            'Headers',
            'Method',
            'QueryString',
            'Body',
            'Data',
            'Pager',
            'ResponseHeaders',
            'ResponseHeadersByName',
            'StatusCode',
            'ReasonPhrase',
            'ResponseBody',
            'CurlInfo',
            'CacheResponse',
            'CachePostResponse',
            'Expiry',
            'Flush',
            'ResponseCacheKeyCallback',
            'ThrowHttpErrors',
            'FollowRedirects',
            'MaxRedirects',
            'HandleCookies',
            'CookieCacheKey',
            'RetryAfterTooManyRequests',
            'RetryAfterMaxSeconds',
            'ExpectJson',
            'PostJson',
            'PreserveKeys',
            'DateFormatter',
            'UserAgent',
            'AlwaysPaginate',
            'ObjectAsArray',
        ];
    }

    /**
     * @internal
     */
    public static function getWritable(): array
    {
        return [
            'CacheResponse',
            'CachePostResponse',
            'Expiry',
            'Flush',
            'ResponseCacheKeyCallback',
            'ThrowHttpErrors',
            'FollowRedirects',
            'MaxRedirects',
            'HandleCookies',
            'CookieCacheKey',
            'RetryAfterTooManyRequests',
            'RetryAfterMaxSeconds',
            'ExpectJson',
            'PostJson',
            'PreserveKeys',
            'DateFormatter',
            'UserAgent',
            'AlwaysPaginate',
            'ObjectAsArray',
        ];
    }

    /**
     * Follow HTTP `Link` headers to retrieve and merge paged JSON data
     *
     * @param array $query
     * @return array All returned entities.
     */
    final public function getAllLinked(?array $query = null): array
    {
        $this->initialise(HttpRequestMethod::GET, $query);
        $entities = [];
        $nextUrl = null;

        do {
            if ($nextUrl) {
                curl_setopt($this->Handle, CURLOPT_URL, $nextUrl);
                $this->clearResponse();
                $nextUrl = null;
            }

            // Collect data from response and move on to next page
            $result = json_decode($this->execute(false), true);
            $entities = array_merge($entities, $result);

            if (preg_match('/<([^>]+)>;\s*rel=([\'"])next\2/', $this->ResponseHeadersByName['link'] ?? '', $matches)) {
                $nextUrl = $matches[1];
            }
        } while ($nextUrl);

        $this->close();

        return $entities;
    }

    /**
     * Follow `$result['links']['next']` to retrieve and merge paged JSON data
     *
     * @param string $entityName Data is retrieved from `$result[$entityName]`.
     * @param array $query
     * @return array All returned entities.
     */
    final public function getAllLinkedByEntity($entityName, ?array $query = null): array
    {
        $this->initialise(HttpRequestMethod::GET, $query);
        $entities = [];
        $nextUrl = null;

        do {
            if ($nextUrl) {
                curl_setopt($this->Handle, CURLOPT_URL, $nextUrl);
                $this->clearResponse();
            }

            // Collect data from response and move on to next page
            $result = json_decode($this->execute(false), true);
            $entities = array_merge($entities, $result[$entityName]);
            $nextUrl = $result['links']['next'] ?? null;
        } while ($nextUrl);

        $this->close();

        return $entities;
    }

    private static function collateNested($data, array $path, array &$entities)
    {
        if (empty($path)) {
            $entities = array_merge($entities, Convert::toList($data));
        } elseif (Test::isListArray($data, true)) {
            foreach ($data as $nested) {
                self::collateNested($nested, $path, $entities);
            }
        } else {
            $field = array_shift($path);

            // Gracefully skip missing data
            if (isset($data[$field])) {
                self::collateNested($data[$field], $path, $entities);
            }
        }
    }

    final public static function walkGraphQL(array &$data, callable $filter = null)
    {
        if (Test::isListArray($data, true)) {
            array_walk(
                $data,
                function (&$data) use ($filter) {
                    if (is_array($data)) {
                        self::walkGraphQL($data, $filter);
                    }
                }
            );

            if ($filter) {
                $data = array_filter($data, $filter);
            }

            return;
        }

        foreach (array_keys($data) as $key) {
            if (substr($key, -10) === 'Connection' &&
                    is_array($data[$key]['nodes'] ?? null) &&
                    !array_key_exists($newKey = substr($key, 0, -10), $data)) {
                $data[$newKey] = $data[$key]['nodes'];
                unset($data[$key]);
                $key = $newKey;
            }

            if (is_array($data[$key])) {
                self::walkGraphQL($data[$key], $filter);
            }
        }
    }

    final public function getByGraphQL(
        string $query,
        array $variables = null,
        string $entityPath = null,
        string $pagePath = null,
        callable $filter = null,
        int $requestLimit = null
    ): array {
        if (!is_null($pagePath) && !(($variables['first'] ?? null) && array_key_exists('after', $variables))) {
            throw new UnexpectedValueException('$first and $after variables are required for pagination');
        }

        $entities = [];
        $nextQuery = [
            'query' => $query,
            'variables' => $variables,
        ];

        do {
            if (!is_null($requestLimit)) {
                if ($requestLimit === 0) {
                    break;
                }

                $requestLimit--;
            }

            $result = $this->post($nextQuery);

            if (!isset($result['data'])) {
                throw new CurlerException($this, 'No data returned');
            }

            $nextQuery = null;
            $objects = [];
            self::collateNested($result['data'], is_null($entityPath) ? null : explode('.', $entityPath), $objects);

            self::walkGraphQL($objects, $filter);

            $entities = array_merge($entities, $objects);

            if (!is_null($pagePath)) {
                $page = [];
                self::collateNested($result['data'], explode('.', $pagePath), $page);

                if (count($page) != 1 ||
                        !isset($page[0]['pageInfo']['endCursor']) ||
                        !isset($page[0]['pageInfo']['hasNextPage'])) {
                    throw new CurlerException($this, 'paginationPath did not resolve to a single object with pageInfo.endCursor and pageInfo.hasNextPage fields');
                }

                if ($page[0]['pageInfo']['hasNextPage']) {
                    $variables['after'] = $page[0]['pageInfo']['endCursor'];
                    $nextQuery = [
                        'query' => $query,
                        'variables' => $variables,
                    ];
                }
            }
        } while ($nextQuery);

        return $entities;
    }

    /**
     * @deprecated
     */
    final public function rawPostJson(string $data, string $mimeType, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::POST, $query, $data, $mimeType);
    }

    /**
     * @deprecated
     */
    final public function getJson(?array $query = null)
    {
        return $this->process(HttpRequestMethod::GET, $query);
    }

    /**
     * @deprecated
     */
    final public function postJson(?array $data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::POST, $query, $data);
    }

    /**
     * @deprecated
     */
    final public function putJson(?array $data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PUT, $query, $data);
    }

    /**
     * @deprecated
     */
    final public function patchJson(?array $data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PATCH, $query, $data);
    }

    /**
     * @deprecated
     */
    final public function deleteJson(?array $data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::DELETE, $query, $data);
    }

    /**
     * Use a fluent interface to create a new Curler object
     *
     */
    public static function build(?IContainer $container = null): CurlerBuilder
    {
        return new CurlerBuilder($container);
    }

    /**
     * @param CurlerBuilder|Curler $object
     */
    public static function resolve($object): Curler
    {
        return CurlerBuilder::resolve($object);
    }
}
