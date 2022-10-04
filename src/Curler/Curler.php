<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use DateTimeInterface;
use Lkrms\Concern\TReadable;
use Lkrms\Concern\TWritable;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IWritable;
use Lkrms\Exception\CurlerException;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\Test;
use Lkrms\Support\DateFormatter;
use RuntimeException;
use UnexpectedValueException;

/**
 * A cURL wrapper optimised for consumption of REST APIs
 *
 * @property-read string|null $Method
 * @property-read string $Url
 * @property-read string|null $Query
 * @property-read CurlerHeaders $Headers
 * @property-read string|array|null $Data
 * @property-read CurlerHeaders|null $ResponseHeaders
 * @property-read array|null $ResponseHeadersByName
 * @property-read int|null $ResponseCode
 * @property-read string|null $ResponseStatus
 * @property-read string|null $ResponseData
 * @property bool $ThrowHttpErrors
 * @property bool $RetryAfterTooManyRequests
 * @property int $RetryAfterMaxSeconds
 * @property bool $PreferDataAsJson
 * @property bool $ForceNumericKeys
 * @property DateFormatter|null $DateFormatter
 */
class Curler implements IReadable, IWritable
{
    use TReadable, TWritable;

    /**
     * @var string|null
     */
    protected $Method;

    /**
     * @var string
     */
    protected $Url;

    /**
     * @var string|null
     */
    protected $Query;

    /**
     * @var CurlerHeaders
     */
    protected $Headers;

    /**
     * @var string|array|null
     */
    protected $Data;

    /**
     * @var CurlerHeaders|null
     */
    protected $ResponseHeaders;

    /**
     * @var array|null
     */
    protected $ResponseHeadersByName;

    /**
     * @var int|null
     */
    protected $ResponseCode;

    /**
     * @var string|null
     */
    protected $ResponseStatus;

    /**
     * @var string|null
     */
    protected $ResponseData;

    /**
     * @var bool
     */
    protected $ThrowHttpErrors = true;

    /**
     * @var bool
     */
    protected $RetryAfterTooManyRequests = false;

    /**
     * @var int
     */
    protected $RetryAfterMaxSeconds = 60;

    /**
     * @var DateFormatter|null
     */
    protected $DateFormatter;

    /**
     * @var bool
     */
    protected $PreferDataAsJson = true;

    /**
     * @var bool
     */
    protected $ForceNumericKeys = false;

    /**
     * Used with calls to Console::debug()
     *
     * @var int
     */
    protected $StackDepth = 0;

    /**
     * @var \CurlHandle|resource|null
     */
    protected $Handle;

    /**
     * @var string|null
     */
    protected static $UserAgent;

    /**
     * @var \CurlMultiHandle|resource|null
     */
    private static $MultiHandle;

    /**
     * @var array
     */
    protected static $MultiInfo = [];

    public static function getReadable(): array
    {
        return [
            "Method",
            "Url",
            "Query",
            "Headers",
            "Data",
            "ResponseHeaders",
            "ResponseHeadersByName",
            "ResponseCode",
            "ResponseStatus",
            "ResponseData",
            "ThrowHttpErrors",
            "RetryAfterTooManyRequests",
            "RetryAfterMaxSeconds",
            "DateFormatter",
            "PreferDataAsJson",
            "ForceNumericKeys",
        ];
    }

    public static function getWritable(): array
    {
        return [
            "ThrowHttpErrors",
            "RetryAfterTooManyRequests",
            "RetryAfterMaxSeconds",
            "DateFormatter",
            "PreferDataAsJson",
            "ForceNumericKeys",
        ];
    }

    protected function _getResponseHeaders(): ?CurlerHeaders
    {
        if (is_null($this->ResponseHeaders))
        {
            return null;
        }
        return clone $this->ResponseHeaders;
    }

    public static function userAgent(string $value = null): ?string
    {
        if (func_num_args())
        {
            self::$UserAgent = $value;
        }
        return self::$UserAgent;
    }

    public function __construct(string $url, CurlerHeaders $headers = null)
    {
        $this->Url     = $url;
        $this->Headers = $headers ?: new CurlerHeaders();
    }

    /**
     * Run curl_getinfo on the underlying CurlHandle
     *
     * Returns `null` if no `CurlHandle` instance has been created.
     *
     * @param int|null $option
     * @return array|null
     */
    public function getCurlInfo(int $option = null): ?array
    {
        if (!$this->Handle)
        {
            return null;
        }

        if (is_null($option))
        {
            return curl_getinfo($this->Handle);
        }
        else
        {
            return curl_getinfo($this->Handle, $option);
        }
    }

    private function resetResponse()
    {
        $this->ResponseHeaders       = new CurlerHeaders();
        $this->ResponseHeadersByName = null;

        $this->ResponseCode   = null;
        $this->ResponseStatus = null;
        $this->ResponseData   = null;
    }

    private function createHandle(string $url)
    {
        $this->Handle = curl_init($url);

        // Return the transfer as a string
        curl_setopt($this->Handle, CURLOPT_RETURNTRANSFER, true);

        // Collect response headers
        curl_setopt(
            $this->Handle,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header)
            {
                if (is_null($this->ResponseStatus))
                {
                    if (count($split = explode(" ", $header, 2)) == 2 &&
                        explode("/", $split[0])[0] == "HTTP")
                    {
                        $this->ResponseStatus = trim($split[1]);
                    }
                    else
                    {
                        throw new CurlerException($this, "Invalid status line in response");
                    }
                }
                else
                {
                    $this->ResponseHeaders->addRawHeader($header);
                }
                return strlen($header);
            }
        );

        // In debug mode, collect request headers
        if (Env::debug())
        {
            curl_setopt($this->Handle, CURLINFO_HEADER_OUT, true);
        }
    }

    private function getQuery(?array $queryString): string
    {
        if (!$queryString)
        {
            return "";
        }
        return "?" . Convert::dataToQuery(
            $queryString,
            $this->ForceNumericKeys,
            $this->DateFormatter
        );
    }

    private function getUrl(?array $queryString): string
    {
        return $this->Url . $this->getQuery($queryString);
    }

    protected function initialise($requestType, ?array $queryString)
    {
        $query = $this->getQuery($queryString);
        $this->createHandle($this->Url . $query);

        switch ($requestType)
        {
            case "GET":

                break;

            case "POST":

                curl_setopt($this->Handle, CURLOPT_POST, true);

                break;

            default:

                // DELETE, PATCH, etc.
                curl_setopt($this->Handle, CURLOPT_CUSTOMREQUEST, $requestType);

                break;
        }

        $this->Headers->unsetHeader("Content-Type");
        $this->Method = $requestType;
        $this->Query  = $query;
        $this->Data   = null;
        $this->resetResponse();
    }

    protected function setData(?array $data, ?bool $asJson)
    {
        if (is_null($data))
        {
            $query = "";
        }
        else
        {
            if (is_null($asJson))
            {
                $asJson = $this->PreferDataAsJson;
            }

            $hasFile = false;
            array_walk_recursive($data,
                function (&$value) use (&$hasFile)
                {
                    if ($value instanceof CurlerFile)
                    {
                        $value   = $value->getCurlFile();
                        $hasFile = true;
                    }
                    elseif ($value instanceof DateTimeInterface)
                    {
                        if (is_null($this->DateFormatter))
                        {
                            $this->DateFormatter = new DateFormatter();
                        }
                        $value = $this->DateFormatter->format($value);
                    }
                });

            if ($hasFile)
            {
                $query = $data;
            }
            elseif ($asJson)
            {
                $this->Headers->setHeader("Content-Type", "application/json");
                $query = json_encode($data);
            }
            else
            {
                $query = Convert::dataToQuery(
                    $data,
                    $this->ForceNumericKeys,
                    $this->DateFormatter
                );
            }
        }

        curl_setopt($this->Handle, CURLOPT_POSTFIELDS, $query);
        $this->Data = $query;
    }

    protected function execute($close = true): string
    {
        // Console::debug() should print the details of whatever called a Curler
        // public method, i.e. not execute(), not get(), but one frame deeper
        $depth = $this->StackDepth + 2;

        // Reset it now in case there's an error later
        $this->StackDepth = 0;

        if (!$this->Headers->hasHeader("User-Agent"))
        {
            if (is_null(self::$UserAgent))
            {
                self::$UserAgent = implode(" ", [
                    str_replace("/", "~", Composer::getRootPackageName())
                    . "/" . Composer::getRootPackageVersion(),
                    "php/" . PHP_VERSION
                ]);
            }
            $this->Headers->setHeader("User-Agent", self::$UserAgent);
        }

        // Add headers for authentication etc.
        curl_setopt($this->Handle, CURLOPT_HTTPHEADER, $this->Headers->getHeaders());

        if (is_null(self::$MultiHandle))
        {
            self::$MultiHandle = curl_multi_init();
        }

        for ($attempt = 0; $attempt < 2; $attempt++)
        {
            // Clear any previous response headers
            $this->resetResponse();

            if (Env::debug() || $this->Method != "GET")
            {
                Console::debug("{$this->Method} " . curl_getinfo(
                    $this->Handle, CURLINFO_EFFECTIVE_URL
                ), null, null, $depth);
            }

            // Execute the request
            curl_multi_add_handle(self::$MultiHandle, $this->Handle);
            $active = null;
            $error  = null;

            do
            {
                if (($status = curl_multi_exec(self::$MultiHandle, $active)) !== CURLM_OK)
                {
                    throw new RuntimeException("cURL multi error: " . curl_multi_strerror($status));
                }

                if ($active)
                {
                    if (curl_multi_select(self::$MultiHandle) == -1)
                    {
                        // 100 milliseconds, as suggested here:
                        // https://curl.se/libcurl/c/curl_multi_fdset.html
                        usleep(100000);
                    }
                }

                while (($message = curl_multi_info_read(self::$MultiHandle)) !== false)
                {
                    self::$MultiInfo[] = $message;
                }
            }
            while ($active);

            // Claim messages that apply to this instance
            foreach (self::$MultiInfo as $i => $message)
            {
                if ($message["handle"] === $this->Handle)
                {
                    if ($message["result"] !== CURLE_OK)
                    {
                        $error = $message["result"];
                    }

                    unset(self::$MultiInfo[$i]);
                }
            }

            // Save transfer information
            $this->ResponseHeadersByName = $this->ResponseHeaders->getHeaderValues(CurlerHeadersFlag::COMBINE_REPEATED);

            if (is_null($error))
            {
                $this->ResponseData = curl_multi_getcontent($this->Handle);
                $this->ResponseCode = (int)curl_getinfo($this->Handle, CURLINFO_RESPONSE_CODE);
            }

            curl_multi_remove_handle(self::$MultiHandle, $this->Handle);

            if (!is_null($error))
            {
                throw new CurlerException($this, "cURL error: " . curl_strerror($error));
            }

            if ($this->RetryAfterTooManyRequests &&
                $attempt == 0 &&
                $this->ResponseCode == 429 &&
                !is_null($after = $this->getLastRetryAfter()) &&
                ($this->RetryAfterMaxSeconds == 0 || $after <= $this->RetryAfterMaxSeconds))
            {
                // Sleep for at least one second
                $after = max(1, $after);
                Console::debug("Received HTTP error 429 Too Many Requests, sleeping for {$after}s", null, null, $depth);
                sleep($after);

                continue;
            }

            break;
        }

        if ($this->ResponseCode >= 400 && $this->ThrowHttpErrors)
        {
            throw new CurlerException($this, "HTTP error " . $this->ResponseStatus);
        }

        if ($close)
        {
            curl_close($this->Handle);
            $this->Handle = null;
        }

        return $this->ResponseData;
    }

    /**
     * @deprecated
     */
    public function getBaseUrl(): string
    {
        return $this->Url;
    }

    /**
     * @deprecated
     */
    public function getHeaders(): CurlerHeaders
    {
        return $this->Headers;
    }

    /**
     * @deprecated
     */
    public function getThrowHttpError(): bool
    {
        return $this->ThrowHttpErrors;
    }

    /**
     * @deprecated
     */
    public function getAutoRetryAfter(): bool
    {
        return $this->RetryAfterTooManyRequests;
    }

    /**
     * @deprecated
     */
    public function getAutoRetryAfterMax(): int
    {
        return $this->RetryAfterMaxSeconds;
    }

    /**
     * @deprecated Use {@see \Lkrms\Utility\Environment::debug()}
     */
    public function getDebug(): bool
    {
        return Env::debug();
    }

    /**
     * @deprecated
     */
    public function getDataAsJson(): bool
    {
        return $this->PreferDataAsJson;
    }

    /**
     * @deprecated
     */
    public function getForceNumericKeys(): bool
    {
        return $this->ForceNumericKeys;
    }

    /**
     * @deprecated
     */
    public function setForceNumericKeys(bool $value)
    {
        $this->ForceNumericKeys = $value;
    }

    /**
     * @deprecated
     */
    public function enableThrowHttpError()
    {
        $this->ThrowHttpErrors = true;
    }

    /**
     * @deprecated
     */
    public function disableThrowHttpError()
    {
        $this->ThrowHttpErrors = false;
    }

    /**
     * @deprecated
     */
    public function enableAutoRetryAfter()
    {
        $this->RetryAfterTooManyRequests = true;
    }

    /**
     * @deprecated
     */
    public function disableAutoRetryAfter()
    {
        $this->RetryAfterTooManyRequests = false;
    }

    /**
     * @deprecated
     */
    public function setMaxRetryAfter(int $seconds)
    {
        $this->_setRetryAfterMaxSeconds($seconds);
    }

    /**
     * @internal
     */
    protected function _setRetryAfterMaxSeconds(int $value): void
    {
        if ($value < 0)
        {
            throw new UnexpectedValueException("value must be greater than or equal to 0");
        }

        $this->RetryAfterMaxSeconds = $value;
    }

    /**
     * @deprecated Use {@see \Lkrms\Utility\Environment::debug()}
     */
    public function enableDebug()
    {
        Env::debug(true);
    }

    /**
     * @deprecated Use {@see \Lkrms\Utility\Environment::debug()}
     */
    public function disableDebug()
    {
        Env::debug(false);
    }

    /**
     * @deprecated
     */
    public function enableDataAsJson()
    {
        $this->PreferDataAsJson = true;
    }

    /**
     * @deprecated
     */
    public function disableDataAsJson()
    {
        $this->PreferDataAsJson = false;
    }

    public function get(array $queryString = null): string
    {
        $this->initialise("GET", $queryString);

        return $this->execute();
    }

    public function head(array $queryString = null): CurlerHeaders
    {
        $this->initialise("HEAD", $queryString);
        $this->execute();

        return $this->ResponseHeaders;
    }

    public function getJson(array $queryString = null)
    {
        $this->StackDepth = 1;

        return json_decode($this->get($queryString), true);
    }

    public function post(array $data = null, array $queryString = null, bool $dataAsJson = null): string
    {
        $this->initialise("POST", $queryString);
        $this->setData($data, $dataAsJson);

        return $this->execute();
    }

    public function postJson(array $data = null, array $queryString = null, bool $dataAsJson = null)
    {
        $this->StackDepth = 1;

        return json_decode($this->post($data, $queryString, $dataAsJson), true);
    }

    public function rawPost(string $data, string $contentType, array $queryString = null): string
    {
        $this->initialise("POST", $queryString);
        $this->Headers->setHeader("Content-Type", $contentType);
        curl_setopt($this->Handle, CURLOPT_POSTFIELDS, $data);

        return $this->execute();
    }

    public function rawPostJson(string $data, string $contentType, array $queryString = null)
    {
        $this->StackDepth = 1;

        return json_decode($this->rawPost($data, $contentType, $queryString), true);
    }

    public function put(array $data = null, array $queryString = null, bool $dataAsJson = null): string
    {
        $this->initialise("PUT", $queryString);
        $this->setData($data, $dataAsJson);

        return $this->execute();
    }

    public function putJson(array $data = null, array $queryString = null, bool $dataAsJson = null)
    {
        $this->StackDepth = 1;

        return json_decode($this->put($data, $queryString, $dataAsJson), true);
    }

    public function patch(array $data = null, array $queryString = null, bool $dataAsJson = null): string
    {
        $this->initialise("PATCH", $queryString);
        $this->setData($data, $dataAsJson);

        return $this->execute();
    }

    public function patchJson(array $data = null, array $queryString = null, bool $dataAsJson = null)
    {
        $this->StackDepth = 1;

        return json_decode($this->patch($data, $queryString, $dataAsJson), true);
    }

    public function delete(array $data = null, array $queryString = null, bool $dataAsJson = null): string
    {
        $this->initialise("DELETE", $queryString);
        $this->setData($data, $dataAsJson);

        return $this->execute();
    }

    public function deleteJson(array $data = null, array $queryString = null, bool $dataAsJson = null)
    {
        $this->StackDepth = 1;

        return json_decode($this->delete($data, $queryString, $dataAsJson), true);
    }

    public function getLastRetryAfter(): ?int
    {
        $retryAfter = $this->ResponseHeadersByName["retry-after"] ?? null;

        if (!is_null($retryAfter))
        {
            if (preg_match("/^[0-9]+\$/", $retryAfter))
            {
                $retryAfter = (int)$retryAfter;
            }
            elseif (($retryAfter = strtotime($retryAfter)) !== false)
            {
                $retryAfter = max(0, $retryAfter - time());
            }
            else
            {
                $retryAfter = null;
            }
        }

        return $retryAfter;
    }

    /**
     * @deprecated
     */
    public function getLastRequestType(): ?string
    {
        return $this->Method;
    }

    /**
     * @deprecated
     */
    public function getLastQuery(): ?string
    {
        return $this->Query;
    }

    /**
     * @deprecated
     */
    public function getLastRequestData()
    {
        return $this->Data;
    }

    /**
     * @deprecated
     */
    public function getLastResponse(): ?string
    {
        return $this->ResponseData;
    }

    /**
     * @deprecated
     */
    public function getLastResponseCode(): ?int
    {
        return $this->ResponseCode;
    }

    /**
     * @deprecated
     */
    public function getLastResponseHeaders(): ?array
    {
        return $this->ResponseHeadersByName;
    }

    /**
     * @deprecated
     */
    public function getLastStatusLine(): ?string
    {
        return $this->ResponseStatus;
    }

    /**
     * Return data from a JSON endpoint by fetching subsequent pages until there
     * are no more results
     *
     * Iterates over each item returned by the endpoint. If the endpoint doesn't
     * return a list, iterates over each page.
     *
     * @param array<string,mixed> $queryString The first element must be the
     * page number parameter. It will be incremented after each request.
     * @param callable|string|null $selector If set, data will be taken from:
     * - `$selector($result)` if `$selector` is callable, or
     * - `$result[$selector]` if `$selector` is a string
     * @return iterable
     */
    public function getAllByPage(array $queryString, $selector = null): iterable
    {
        if (!is_int(reset($queryString)) ||
            is_null($pageKey = key($queryString)))
        {
            throw new UnexpectedValueException("First queryString element is not a page number");
        };

        $this->initialise("GET", $queryString);
        $nextUrl = null;

        try
        {
            do
            {
                if ($nextUrl)
                {
                    curl_setopt($this->Handle, CURLOPT_URL, $nextUrl);
                    $nextUrl = null;
                }

                $page = json_decode($this->execute(false), true);
                if ($selector)
                {
                    if (is_callable($selector))
                    {
                        $page = $selector($page);
                    }
                    elseif (is_string($selector))
                    {
                        $page = $page[$selector];
                    }
                    else
                    {
                        throw new UnexpectedValueException("Invalid selector");
                    }
                }

                $yielded = 0;
                foreach (Convert::toList($page) as $entry)
                {
                    yield $entry;
                    $yielded++;
                }

                if ($yielded)
                {
                    $queryString[$pageKey]++;
                    $nextUrl = $this->getUrl($queryString);
                }
            }
            while ($nextUrl);
        }
        finally
        {
            curl_close($this->Handle);
        }
    }

    /**
     * Follow HTTP `Link` headers to retrieve and merge paged JSON data
     *
     * @param array $queryString
     * @return array All returned entities.
     */
    public function getAllLinked(array $queryString = null): array
    {
        $this->initialise("GET", $queryString);
        $entities = [];
        $nextUrl  = null;

        do
        {
            if ($nextUrl)
            {
                curl_setopt($this->Handle, CURLOPT_URL, $nextUrl);
                $nextUrl = null;
            }

            // Collect data from response and move on to next page
            $result   = json_decode($this->execute(false), true);
            $entities = array_merge($entities, $result);

            if (preg_match("/<([^>]+)>;\\s*rel=(['\"])next\\2/", $this->ResponseHeadersByName["link"] ?? "", $matches))
            {
                $nextUrl = $matches[1];
            }
        }
        while ($nextUrl);

        curl_close($this->Handle);

        return $entities;
    }

    /**
     * Follow `$result['links']['next']` to retrieve and merge paged JSON data
     *
     * @param string $entityName Data is retrieved from `$result[$entityName]`.
     * @param array $queryString
     * @return array All returned entities.
     */
    public function getAllLinkedByEntity($entityName, array $queryString = null): array
    {
        $this->initialise("GET", $queryString);
        $entities = [];
        $nextUrl  = null;

        do
        {
            if ($nextUrl)
            {
                curl_setopt($this->Handle, CURLOPT_URL, $nextUrl);
            }

            // Collect data from response and move on to next page
            $result   = json_decode($this->execute(false), true);
            $entities = array_merge($entities, $result[$entityName]);
            $nextUrl  = $result["links"]["next"] ?? null;
        }
        while ($nextUrl);

        curl_close($this->Handle);

        return $entities;
    }

    public function getAllLinkedByOData(array $queryString = null, string $prefix = null)
    {
        $this->initialise("GET", $queryString);
        $entities = [];
        $nextUrl  = null;

        do
        {
            if ($nextUrl)
            {
                curl_setopt($this->Handle, CURLOPT_URL, $nextUrl);
            }

            // Collect data from response and move on to next page
            $result = json_decode($this->execute(false), true);

            if (is_null($prefix))
            {
                if ($this->ResponseHeadersByName["odata-version"] == "4.0")
                {
                    $prefix = "@odata.";
                }
                else
                {
                    $prefix = "@";
                }
            }

            $entities = array_merge($entities, $result["value"]);
            $nextUrl  = $result[$prefix . "nextLink"] ?? null;
        }
        while ($nextUrl);

        curl_close($this->Handle);

        return $entities;
    }

    private static function collateNested($data, array $path, array & $entities)
    {
        if (empty($path))
        {
            $entities = array_merge($entities, Convert::toList($data));
        }
        elseif (Test::isListArray($data, true))
        {
            foreach ($data as $nested)
            {
                self::collateNested($nested, $path, $entities);
            }
        }
        else
        {
            $field = array_shift($path);

            // Gracefully skip missing data
            if (isset($data[$field]))
            {
                self::collateNested($data[$field], $path, $entities);
            }
        }
    }

    public static function walkGraphQL(array & $data, callable $filter = null)
    {
        if (Test::isListArray($data, true))
        {
            array_walk($data, function (&$data) use ($filter)
            {
                if (is_array($data))
                {
                    self::walkGraphQL($data, $filter);
                }
            });

            if ($filter)
            {
                $data = array_filter($data, $filter);
            }

            return;
        }

        foreach (array_keys($data) as $key)
        {
            if (substr($key, -10) == "Connection" &&
                is_array($data[$key]["nodes"] ?? null) &&
                !array_key_exists($newKey = substr($key, 0, -10), $data))
            {
                $data[$newKey] = $data[$key]["nodes"];
                unset($data[$key]);
                $key = $newKey;
            }

            if (is_array($data[$key]))
            {
                self::walkGraphQL($data[$key], $filter);
            }
        }
    }

    public function getByGraphQL(
        string $query,
        array $variables   = null,
        string $entityPath = null,
        string $pagePath   = null,
        callable $filter   = null,
        int $requestLimit  = null
    ): array
    {
        if (!is_null($pagePath) && !(($variables["first"] ?? null) && array_key_exists("after", $variables)))
        {
            throw new CurlerException($this, "\$first and \$after variables are required for pagination");
        }

        $entities  = [];
        $nextQuery = [
            "query"     => $query,
            "variables" => $variables,
        ];

        do
        {
            if (!is_null($requestLimit))
            {
                if ($requestLimit == 0)
                {
                    break;
                }

                $requestLimit--;
            }

            $this->StackDepth = 1;

            $result = json_decode($this->post($nextQuery), true);

            if (!isset($result["data"]))
            {
                throw new CurlerException($this, "No data returned");
            }

            $nextQuery = null;
            $objects   = [];
            self::collateNested($result["data"], is_null($entityPath) ? null : explode(".", $entityPath), $objects);

            self::walkGraphQL($objects, $filter);

            $entities = array_merge($entities, $objects);

            if (!is_null($pagePath))
            {
                $page = [];
                self::collateNested($result["data"], explode(".", $pagePath), $page);

                if (count($page) != 1 ||
                    !isset($page[0]["pageInfo"]["endCursor"]) ||
                    !isset($page[0]["pageInfo"]["hasNextPage"]))
                {
                    throw new CurlerException($this, "paginationPath did not resolve to a single object with pageInfo.endCursor and pageInfo.hasNextPage fields");
                }

                if ($page[0]["pageInfo"]["hasNextPage"])
                {
                    $variables["after"] = $page[0]["pageInfo"]["endCursor"];
                    $nextQuery = [
                        "query"     => $query,
                        "variables" => $variables,
                    ];
                }
            }
        }
        while ($nextQuery);

        return $entities;
    }
}
