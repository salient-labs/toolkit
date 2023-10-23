<?php declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Concern\HasBuilder;
use Lkrms\Concern\HasMutator;
use Lkrms\Concern\TReadable;
use Lkrms\Concern\TWritable;
use Lkrms\Contract\IDateFormatter;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IWritable;
use Lkrms\Contract\ProvidesBuilder;
use Lkrms\Curler\Catalog\CurlerProperty;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Exception\CurlerCurlErrorException;
use Lkrms\Curler\Exception\CurlerHttpErrorException;
use Lkrms\Curler\Exception\CurlerInvalidResponseException;
use Lkrms\Curler\Exception\CurlerUnexpectedResponseException;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Console;
use Lkrms\Iterator\RecursiveCallbackIterator;
use Lkrms\Iterator\RecursiveMutableGraphIterator;
use Lkrms\Support\Catalog\HttpHeader;
use Lkrms\Support\Catalog\HttpRequestMethod;
use Lkrms\Support\Catalog\MimeType;
use Lkrms\Support\DateFormatter;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\Test;
use DateTimeInterface;
use Generator;
use LogicException;
use RecursiveIteratorIterator;

/**
 * A cURL wrapper optimised for consumption of REST APIs
 *
 * A (very) lightweight Guzzle alternative.
 *
 * @property-read string $BaseUrl Request endpoint
 * @property-read ICurlerHeaders $Headers Request headers
 * @property-read string|null $Method Most recent request method
 * @property-read string|null $QueryString Query string most recently added to request URL
 * @property-read string|mixed[]|null $Body Request body, as passed to cURL
 * @property-read string|mixed[]|object|null $Data Request body, before serialization
 * @property-read ICurlerPager|null $Pager Pagination handler
 * @property-read ICurlerHeaders|null $ResponseHeaders Response headers
 * @property-read array<string,string>|null $ResponseHeadersByName An array that maps lowercase response headers to their combined values
 * @property-read int|null $StatusCode Response status code
 * @property-read string|null $ReasonPhrase Response status explanation
 * @property-read string|null $ResponseBody Response body
 * @property-read mixed[]|null $CurlInfo curl_getinfo()'s most recent return value
 * @property bool $CacheResponse Cache responses to GET and HEAD requests? (HTTP caching directives are ignored)
 * @property bool $CachePostResponse Cache responses to eligible POST requests?
 * @property int $Expiry Seconds before cached responses expire (0 = no expiry)
 * @property bool $Flush Replace cached responses that haven't expired?
 * @property callable|null $ResponseCacheKeyCallback Override the default cache key when saving and loading cached responses
 * @property bool $ThrowHttpErrors Throw an exception if the status code is >= 400?
 * @property callable|null $ResponseCallback Apply a callback to responses before they are returned
 * @property int|null $ConnectTimeout Override the default number of seconds the connection phase of the transfer is allowed to take
 * @property int|null $Timeout Limit the number of seconds the transfer is allowed to take
 * @property bool $FollowRedirects Follow "Location:" headers?
 * @property int|null $MaxRedirects Limit the number of redirections followed when FollowRedirects is set
 * @property bool $HandleCookies Send and receive cookies?
 * @property string|null $CookieCacheKey Override the default cache key when saving and loading cookies
 * @property bool $RetryAfterTooManyRequests Retry after receiving "429 Too Many Requests" with a Retry-After header?
 * @property int $RetryAfterMaxSeconds Limit the delay between attempts when RetryAfterTooManyRequests is set
 * @property bool $ExpectJson Request JSON from upstream?
 * @property bool $PostJson Use JSON to serialize POST/PUT/PATCH/DELETE data?
 * @property bool $PreserveKeys Suppress removal of numeric indices from serialized lists?
 * @property IDateFormatter|null $DateFormatter Specify the date format and timezone used upstream
 * @property string|null $UserAgent Override the default User-Agent header
 * @property bool $AlwaysPaginate Pass every response to the pager?
 * @property bool $ObjectAsArray Return deserialized objects as associative arrays?
 *
 * @implements ProvidesBuilder<CurlerBuilder>
 */
final class Curler implements IReadable, IWritable, ProvidesBuilder
{
    use TReadable;
    use TWritable;
    use HasBuilder;
    use HasMutator;

    /**
     * Request endpoint
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
     * Most recent request method
     *
     * @var string|null
     */
    protected $Method;

    /**
     * Query string most recently added to request URL
     *
     * Unless empty, {@see Curler::$QueryString} starts with a `?` followed by
     * the query string.
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
     * curl_getinfo()'s most recent return value
     *
     * @var mixed[]|null
     */
    protected $CurlInfo;

    /**
     * Cache responses to GET and HEAD requests? (HTTP caching directives are
     * ignored)
     *
     * If `true`, the shared {@see \Lkrms\Store\CacheStore} instance serviced by
     * {@see Cache} is used as an HTTP response cache. Set
     * {@see Curler::$CachePostResponse} to cache `POST` responses too.
     *
     * Not compliant with [RFC9111], obviously. Use responsibly.
     *
     * @see Curler::$Expiry
     * @see Curler::$Flush
     * @see Curler::$ResponseCacheKeyCallback
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
     * Seconds before cached responses expire (0 = no expiry)
     *
     * Ignored unless {@see Curler::$CacheResponse} is `true`.
     *
     * @var int
     */
    protected $Expiry = 3600;

    /**
     * Replace cached responses that haven't expired?
     *
     * Ignored unless {@see Curler::$CacheResponse} is `true`.
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
     * Apply a callback to responses before they are returned
     *
     * {@see Curler::$ResponseCallback} is called between loading an HTTP
     * response and performing status code checks.
     *
     * @var (callable(Curler): Curler)|null
     */
    protected $ResponseCallback;

    /**
     * Override the default number of seconds the connection phase of the
     * transfer is allowed to take
     *
     * cURL's default connection timeout is 300 seconds.
     *
     * @var int|null
     */
    protected $ConnectTimeout;

    /**
     * Limit the number of seconds the transfer is allowed to take
     *
     * @var int|null
     */
    protected $Timeout;

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
     * {@see Cache} will be used for cookie storage.
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
    protected $RetryAfterMaxSeconds = 300;

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
     * @var IDateFormatter|null
     */
    protected $DateFormatter;

    /**
     * Override the default User-Agent header
     *
     * The default user agent is derived from the name and version of the root
     * package, e.g. `lkrms~util/v0.20.26-ca5d50e7 php/8.2.7`.
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
    private static $Handle;

    /**
     * @var bool|null
     */
    private static $HandleIsReset;

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
     * @param (callable(Curler): Curler)|null $responseCallback
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
        ?callable $responseCallback = null,
        ?int $connectTimeout = null,
        ?int $timeout = null,
        bool $followRedirects = false,
        ?int $maxRedirects = null,
        bool $handleCookies = false,
        ?string $cookieCacheKey = null,
        bool $retryAfterTooManyRequests = false,
        int $retryAfterMaxSeconds = 300,
        bool $expectJson = true,
        bool $postJson = true,
        bool $preserveKeys = false,
        ?IDateFormatter $dateFormatter = null,
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
        $this->ResponseCallback = $responseCallback;
        $this->ConnectTimeout = $connectTimeout;
        $this->Timeout = $timeout;
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
    public function addHeader(string $name, string $value, bool $private = false)
    {
        $this->Headers = $this->Headers->addHeader($name, $value, $private);

        return $this;
    }

    /**
     * @return $this
     */
    public function unsetHeader(string $name, ?string $pattern = null)
    {
        $this->Headers = $this->Headers->unsetHeader($name, $pattern);

        return $this;
    }

    /**
     * @return $this
     */
    public function setHeader(string $name, string $value, bool $private = false)
    {
        $this->Headers = $this->Headers->setHeader($name, $value, $private);

        return $this;
    }

    /**
     * @return $this
     */
    public function addPrivateHeaderName(string $name)
    {
        $this->Headers = $this->Headers->addPrivateHeaderName($name);

        return $this;
    }

    /**
     * @return $this
     */
    public function setContentType(?string $mimeType)
    {
        $this->Headers =
            $mimeType === null
                ? $this->Headers->unsetHeader(HttpHeader::CONTENT_TYPE)
                : $this->Headers->setHeader(HttpHeader::CONTENT_TYPE, $mimeType);

        return $this;
    }

    /**
     * Apply new headers to a clone of the instance
     *
     * @return $this
     */
    public function withHeaders(ICurlerHeaders $headers)
    {
        $clone = $this->mutate();
        $clone->Headers = $headers;

        return $clone;
    }

    /**
     * Apply a new pager to a clone of the instance
     *
     * @return $this
     */
    public function withPager(ICurlerPager $pager)
    {
        $clone = $this->mutate();
        $clone->Pager = $pager;

        return $clone;
    }

    /**
     * Apply a value to a clone of the instance
     *
     * @param string&CurlerProperty::* $property
     * @param mixed $value
     * @return $this
     */
    public function with(string $property, $value)
    {
        if (!in_array($property, $this->getWritable(), true)) {
            throw new LogicException(sprintf('Invalid property: %s', $property));
        }

        return $this->withPropertyValue($property, $value);
    }

    /**
     * @return $this
     */
    public function flushCookies()
    {
        if ($cookieKey = $this->getCookieKey()) {
            Cache::delete($cookieKey);
        }

        return $this;
    }

    public function responseContentTypeIs(string $mimeType): bool
    {
        $contentType = $this->ResponseHeaders->getHeaderValue(
            HttpHeader::CONTENT_TYPE,
            CurlerHeadersFlag::KEEP_LAST
        );

        // Assume JSON if it's expected and no Content-Type is specified
        if ($contentType === null) {
            return !strcasecmp($mimeType, MimeType::JSON) && $this->ExpectJson;
        }

        return MimeType::is($mimeType, $contentType);
    }

    protected function getEffectiveUrl(): ?string
    {
        return self::$Handle
            ? curl_getinfo(self::$Handle, CURLINFO_EFFECTIVE_URL)
            : null;
    }

    protected function close(): void
    {
        if (!self::$Handle) {
            return;
        }

        if ($this->CookieKey && $this->ExecuteCount) {
            Cache::set($this->CookieKey, curl_getinfo(self::$Handle, CURLINFO_COOKIELIST));
        }

        curl_reset(self::$Handle);
        self::$HandleIsReset = true;
    }

    /**
     * @param mixed[]|null $query
     */
    private function initialise(string $method, ?array $query, ?ICurlerPager $pager = null): void
    {
        $this->ExecuteCount = 0;

        if ($pager) {
            $query = $pager->prepareQuery($query);
        }
        $this->QueryString = $this->getQueryString($query);

        if (self::$Handle) {
            if (!self::$HandleIsReset) {
                curl_reset(self::$Handle);
            }
            self::$HandleIsReset = false;
            curl_setopt(self::$Handle, CURLOPT_URL, $this->BaseUrl . $this->QueryString);
        } else {
            self::$Handle = curl_init($this->BaseUrl . $this->QueryString);
        }

        // Return the transfer as a string
        curl_setopt(self::$Handle, CURLOPT_RETURNTRANSFER, true);

        // Enable all supported encoding types (e.g. gzip, deflate) and set
        // Accept-Encoding header accordingly
        curl_setopt(self::$Handle, CURLOPT_ENCODING, '');

        // Collect response headers
        curl_setopt(
            self::$Handle,
            CURLOPT_HEADERFUNCTION,
            fn($curl, $header) => strlen($this->processHeader($header))
        );

        if ($this->ConnectTimeout !== null) {
            curl_setopt(self::$Handle, CURLOPT_CONNECTTIMEOUT, $this->ConnectTimeout);
        }

        if ($this->Timeout !== null) {
            curl_setopt(self::$Handle, CURLOPT_TIMEOUT, $this->Timeout);
        }

        if ($this->FollowRedirects) {
            curl_setopt(self::$Handle, CURLOPT_FOLLOWLOCATION, true);
            if ($this->MaxRedirects !== null) {
                curl_setopt(self::$Handle, CURLOPT_MAXREDIRS, $this->MaxRedirects);
            }
        }

        if ($this->CookieKey = $this->getCookieKey()) {
            // Enable cookies without loading them from a file
            curl_setopt(self::$Handle, CURLOPT_COOKIEFILE, '');

            foreach (Cache::get($this->CookieKey) ?: [] as $cookie) {
                curl_setopt(self::$Handle, CURLOPT_COOKIELIST, $cookie);
            }
        }

        // In debug mode, collect request headers
        if (Env::debug()) {
            curl_setopt(self::$Handle, CURLINFO_HEADER_OUT, true);
        }

        switch ($method) {
            case HttpRequestMethod::GET:
                break;

            case HttpRequestMethod::HEAD:
                curl_setopt(self::$Handle, CURLOPT_NOBODY, true);
                break;

            case HttpRequestMethod::POST:
                curl_setopt(self::$Handle, CURLOPT_POST, true);
                break;

            case HttpRequestMethod::PUT:
            case HttpRequestMethod::PATCH:
            case HttpRequestMethod::DELETE:
            case HttpRequestMethod::CONNECT:
            case HttpRequestMethod::OPTIONS:
            case HttpRequestMethod::TRACE:
                curl_setopt(self::$Handle, CURLOPT_CUSTOMREQUEST, $method);
                break;

            default:
                throw new LogicException("Invalid HTTP request method: $method");
        }

        $this->Method = $method;

        $this->clearResponse();
        $this->setContentType(null);
        $this->Body = $this->Data = null;

        if ($pager) {
            if ($pager->prepareCurler($this) !== $this) {
                throw new LogicException(sprintf(
                    '%s::prepareCurler() returned a different instance', get_class($pager)
                ));
            }
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

    /**
     * @param mixed[]|null $query
     */
    public function getQueryUrl(?array $query): string
    {
        return $this->BaseUrl . $this->getQueryString($query);
    }

    /**
     * @param mixed[]|null $query
     */
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

    private function processHeader(string $header): string
    {
        if (substr($header, 0, 5) === 'HTTP/') {
            if (count($split = explode(' ', $header, 3)) < 2) {
                throw new CurlerInvalidResponseException('Invalid status line in response', $this);
            }
            $this->ResponseHeaders = new CurlerHeaders();
            $this->ReasonPhrase = trim($split[2] ?? '');
            return $header;
        }
        if ($this->ReasonPhrase === null) {
            throw new CurlerInvalidResponseException('No status line in response', $this);
        }
        $this->ResponseHeaders = $this->ResponseHeaders->addRawHeader($header);
        return $header;
    }

    private function clearResponse(): void
    {
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
            self::$Handle,
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
        $iterator = new RecursiveMutableGraphIterator($data);
        // Treat `CurlerFile` and `DateTimeInterface` instances as leaf nodes
        $iterator = new RecursiveCallbackIterator(
            $iterator,
            fn($value) =>
                !($value instanceof CurlerFile ||
                    $value instanceof DateTimeInterface)
        );
        $leaves = new RecursiveIteratorIterator($iterator);
        $iterator = new RecursiveIteratorIterator(
            $iterator, RecursiveIteratorIterator::SELF_FIRST
        );

        // Does `$data` contain a `CurlerFile`?
        $file = false;
        foreach ($leaves as $value) {
            if ($value instanceof CurlerFile) {
                $file = true;
                break;
            }
        }

        // With that answered, start over, replacing `CurlerFile` and
        // `DateTimeInterface` instances
        foreach ($iterator as $value) {
            $replace = null;

            if ($value instanceof CurlerFile) {
                $replace = $value->getCurlFile();
            } elseif ($value instanceof DateTimeInterface) {
                $replace = $this->getDateFormatter()->format($value);
            } elseif (!$file) {
                continue;
            }

            /** @var RecursiveCallbackIterator<array-key,mixed> */
            $inner = $iterator->getInnerIterator();
            /** @var RecursiveMutableGraphIterator */
            $inner = $inner->getInnerIterator();

            if ($replace !== null) {
                $inner->replace($replace);
                continue;
            }

            // If uploading a file, replace every object that isn't a CURLFile
            // with an array cURL can encode
            if ($file) {
                $inner->maybeConvertToArray();
            }
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

    private function getDateFormatter(): IDateFormatter
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
                $this->ResponseHeaders->getHeaderValues(CurlerHeadersFlag::COMBINE);

            return $this->ResponseBody;
        }

        $this->ExecuteCount++;

        curl_setopt(self::$Handle, CURLOPT_HTTPHEADER, $this->Headers->getHeaders());

        $attempt = 0;
        while ($attempt++ < 2) {
            if (!in_array($this->Method, [HttpRequestMethod::GET, HttpRequestMethod::HEAD]) || Env::debug()) {
                // Console::debug() should print the details of whatever called
                // one of Curler's public methods, i.e. not execute(), not
                // get(), but one frame deeper
                Console::debug(
                    "{$this->Method} " . rawurldecode($this->getEffectiveUrl()),
                    null,
                    null,
                    $depth + 3
                );
            }

            // Execute the request
            $result = curl_exec(self::$Handle);
            $this->CurlInfo = curl_getinfo(self::$Handle);

            if ($result === false) {
                $error = curl_errno(self::$Handle);
                throw new CurlerCurlErrorException($error, $this);
            }

            // ReasonPhrase is set by processHeader()
            $this->ResponseHeadersByName = $this->ResponseHeaders->getHeaderValues(CurlerHeadersFlag::COMBINE);
            $this->StatusCode = $this->CurlInfo['http_code'];
            $this->ResponseBody = $result;

            if ($this->ResponseCallback) {
                if (($this->ResponseCallback)($this) !== $this) {
                    throw new LogicException(sprintf(
                        '%s::$ResponseCallback returned a different instance', static::class
                    ));
                }
            }

            if ($this->StatusCode === 429 &&
                    $this->RetryAfterTooManyRequests &&
                    $attempt === 1 &&
                    ($after = $this->getRetryAfter()) !== null &&
                    ($this->RetryAfterMaxSeconds === 0 || $after <= $this->RetryAfterMaxSeconds)) {
                // Sleep for at least one second
                $after = max(1, $after);
                Console::debug(sprintf(
                    'Received "429 Too Many Requests", sleeping for %ds', $after
                ), null, null, $depth + 3);
                sleep($after);

                $this->clearResponse();
                continue;
            }

            break;
        }

        if ($this->StatusCode >= 400 && $this->ThrowHttpErrors) {
            throw new CurlerHttpErrorException(
                $this->StatusCode,
                $this->ReasonPhrase,
                $this,
            );
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
     * @param mixed[]|null $query
     */
    public function head(?array $query = null): ICurlerHeaders
    {
        return $this->process(HttpRequestMethod::HEAD, $query);
    }

    /**
     * @param mixed[]|null $query
     * @return mixed
     */
    public function get(?array $query = null)
    {
        return $this->process(HttpRequestMethod::GET, $query);
    }

    /**
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function post($data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::POST, $query, $data);
    }

    /**
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function put($data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PUT, $query, $data);
    }

    /**
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function patch($data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PATCH, $query, $data);
    }

    /**
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return mixed
     */
    public function delete($data = null, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::DELETE, $query, $data);
    }

    /**
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function getP(?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::GET, $query);
    }

    /**
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function postP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::POST, $query, $data);
    }

    /**
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function putP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::PUT, $query, $data);
    }

    /**
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function patchP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::PATCH, $query, $data);
    }

    /**
     * @param mixed[]|object|null $data
     * @param mixed[]|null $query
     * @return iterable<mixed>
     */
    public function deleteP($data = null, ?array $query = null): iterable
    {
        return $this->paginate(HttpRequestMethod::DELETE, $query, $data);
    }

    /**
     * @param mixed[]|null $query
     * @return mixed
     */
    public function rawPost(string $data, string $mimeType, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::POST, $query, $data, $mimeType);
    }

    /**
     * @param mixed[]|null $query
     * @return mixed
     */
    public function rawPut(string $data, string $mimeType, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PUT, $query, $data, $mimeType);
    }

    /**
     * @param mixed[]|null $query
     * @return mixed
     */
    public function rawPatch(string $data, string $mimeType, ?array $query = null)
    {
        return $this->process(HttpRequestMethod::PATCH, $query, $data, $mimeType);
    }

    /**
     * @param mixed[]|null $query
     * @return mixed
     */
    public function rawDelete(string $data, string $mimeType, ?array $query = null)
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
        $isRaw = $mimeType !== null;
        if ($this->Pager && $this->AlwaysPaginate && !$isRaw) {
            $pager = $this->Pager;
            if (!in_array($method, [HttpRequestMethod::GET, HttpRequestMethod::HEAD])) {
                $data = $pager->prepareData($data);
            }
        }

        $this->initialise($method, $query, $pager ?? null);

        $this->Data = $data;
        if (is_array($data) || is_object($data)) {
            $this->applyData($data);
        } elseif (is_string($data) && $mimeType) {
            curl_setopt(self::$Handle, CURLOPT_POSTFIELDS, $data);
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
     * @return Generator<mixed>
     */
    private function paginate(string $method, ?array $query, $data = null): Generator
    {
        if (!$this->Pager) {
            throw new LogicException(static::class . '::$Pager is not set');
        }
        if (!in_array($method, [HttpRequestMethod::GET, HttpRequestMethod::HEAD])) {
            $data = $this->Pager->prepareData($data);
        }

        $this->initialise($method, $query, $this->Pager);

        do {
            if ($data !== null) {
                $this->Data = $data;
                $this->applyData($data);
            }

            $this->execute(false);

            if ($this->responseContentTypeIs(MimeType::JSON)) {
                $response = json_decode($this->ResponseBody, $this->ObjectAsArray);
            } else {
                throw new CurlerUnexpectedResponseException('Unable to deserialize response', $this);
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
            curl_setopt(self::$Handle, CURLOPT_URL, $url);
            if ($headers !== null) {
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
            'ConnectTimeout',
            'Timeout',
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
            'ResponseCallback',
            'ConnectTimeout',
            'Timeout',
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
     * @param mixed[] $query
     * @return mixed[] All returned entities.
     */
    public function getAllLinked(?array $query = null): array
    {
        $this->initialise(HttpRequestMethod::GET, $query);
        $entities = [];
        $nextUrl = null;

        do {
            if ($nextUrl) {
                curl_setopt(self::$Handle, CURLOPT_URL, $nextUrl);
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
     * @param mixed[] $query
     * @return mixed[] All returned entities.
     */
    public function getAllLinkedByEntity($entityName, ?array $query = null): array
    {
        $this->initialise(HttpRequestMethod::GET, $query);
        $entities = [];
        $nextUrl = null;

        do {
            if ($nextUrl) {
                curl_setopt(self::$Handle, CURLOPT_URL, $nextUrl);
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

    /**
     * @param mixed $data
     * @param string[] $path
     * @param mixed[] $entities
     */
    private static function collateNested($data, array $path, array &$entities): void
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

    /**
     * @param mixed[] $data
     */
    final public static function walkGraphQL(array &$data, callable $filter = null): void
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

    /**
     * @param array<string,mixed>|null $variables
     * @return mixed[]
     */
    public function getByGraphQL(
        string $query,
        array $variables = null,
        string $entityPath = null,
        string $pagePath = null,
        callable $filter = null,
        int $requestLimit = null
    ): array {
        if ($pagePath !== null && !(($variables['first'] ?? null) && array_key_exists('after', $variables))) {
            throw new LogicException('$first and $after variables are required for pagination');
        }

        $entities = [];
        $nextQuery = [
            'query' => $query,
            'variables' => $variables,
        ];

        do {
            if ($requestLimit !== null) {
                if ($requestLimit === 0) {
                    break;
                }

                $requestLimit--;
            }

            $result = $this->post($nextQuery);

            if (!isset($result['data'])) {
                throw new CurlerUnexpectedResponseException('No data returned', $this);
            }

            $nextQuery = null;
            $objects = [];
            self::collateNested($result['data'], is_null($entityPath) ? null : explode('.', $entityPath), $objects);

            self::walkGraphQL($objects, $filter);

            $entities = array_merge($entities, $objects);

            if ($pagePath !== null) {
                $page = [];
                self::collateNested($result['data'], explode('.', $pagePath), $page);

                if (count($page) != 1 ||
                        !isset($page[0]['pageInfo']['endCursor']) ||
                        !isset($page[0]['pageInfo']['hasNextPage'])) {
                    throw new CurlerUnexpectedResponseException('paginationPath did not resolve to a single object with pageInfo.endCursor and pageInfo.hasNextPage fields', $this);
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
     * @inheritDoc
     */
    public static function getBuilder(): string
    {
        return CurlerBuilder::class;
    }
}
