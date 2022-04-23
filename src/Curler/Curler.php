<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use CurlHandle;
use CurlMultiHandle;
use Lkrms\Console\Console;
use Lkrms\Convert;
use Lkrms\Env;
use Lkrms\Exception\CurlerException;
use Lkrms\Test;
use RuntimeException;
use UnexpectedValueException;

/**
 * For easy consumption of REST APIs
 *
 * @package Lkrms
 */
class Curler
{
    /**
     * @var string
     */
    protected $BaseUrl;

    /**
     * @var CurlerHeaders
     */
    protected $Headers;

    /**
     * @var array|null
     */
    protected $ResponseHeaders;

    /**
     * @var string|null
     */
    protected $LastRequestType;

    /**
     * @var string|null
     */
    protected $LastQuery;

    /**
     * @var string|array|null
     */
    protected $LastRequestData;

    /**
     * @var array|null
     */
    protected $LastCurlInfo;

    /**
     * @var string|null
     */
    protected $LastResponse;

    /**
     * @var int|null
     */
    protected $LastResponseCode;

    /**
     * @var array|null
     */
    protected $LastResponseHeaders;

    /**
     * @var bool
     */
    protected $ThrowHttpError = true;

    /**
     * @var bool
     */
    protected $AutoRetryAfter = false;

    /**
     * @var int
     */
    protected $AutoRetryAfterMax = 60;

    /**
     * @var bool
     */
    protected $Debug;

    /**
     * @var bool
     */
    protected $DataAsJson = true;

    /**
     * @var bool
     */
    protected $ForceNumericKeys = false;

    /**
     * Used with calls to Console::debug()
     *
     * @var int
     */
    protected $InternalStackDepth = 0;

    /**
     * @var CurlHandle|null
     */
    protected $Handle;

    /**
     * @var CurlMultiHandle|null
     */
    private static $MultiHandle;

    /**
     * @var array
     */
    protected static $MultiInfo = [];

    public function __construct(string $baseUrl, CurlerHeaders $headers = null)
    {
        $this->BaseUrl = $baseUrl;
        $this->Headers = $headers ?: new CurlerHeaders();
        $this->Debug   = Env::debug();
    }

    private function createHandle(string $url)
    {
        $this->Handle = curl_init($url);

        // Don't send output to browser
        curl_setopt($this->Handle, CURLOPT_RETURNTRANSFER, true);

        // Collect response headers
        curl_setopt(
            $this->Handle,
            CURLOPT_HEADERFUNCTION,
            function ($curl, $header)
            {
                $split = explode(":", $header, 2);

                if (count($split) == 2)
                {
                    list ($name, $value) = $split;

                    // Header field names are case-insensitive
                    $name  = strtolower($name);
                    $value = trim($value);
                    $this->ResponseHeaders[$name] = $value;
                }

                return strlen($header);
            }
        );

        // In debug mode, collect request headers
        if ($this->Debug)
        {
            curl_setopt($this->Handle, CURLINFO_HEADER_OUT, true);
        }
    }

    protected function initialise($requestType, ?array $queryString)
    {
        if (empty($queryString))
        {
            $query = "";
        }
        else
        {
            $query = "?" . Convert::dataToQuery($queryString, $this->ForceNumericKeys);
        }

        $this->createHandle($this->BaseUrl . $query);

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
        $this->LastRequestType     = $requestType;
        $this->LastQuery           = $query;
        $this->LastRequestData     = null;
        $this->LastCurlInfo        = null;
        $this->LastResponse        = null;
        $this->LastResponseCode    = null;
        $this->LastResponseHeaders = null;
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
                $asJson = $this->DataAsJson;
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
                $query = Convert::dataToQuery($data, $this->ForceNumericKeys);
            }
        }

        curl_setopt($this->Handle, CURLOPT_POSTFIELDS, $query);
        $this->LastRequestData = $query;
    }

    protected function execute($close = true): string
    {
        // Console::debug() should print the details of whatever called a Curler
        // public method, i.e. not \Execute, not \Get, but one frame deeper
        $depth = $this->InternalStackDepth + 2;

        // Reset it now in case there's an error later
        $this->InternalStackDepth = 0;

        // Add headers for authentication etc.
        curl_setopt($this->Handle, CURLOPT_HTTPHEADER, $this->Headers->getHeaders());

        if (is_null(self::$MultiHandle))
        {
            self::$MultiHandle = curl_multi_init();
        }

        for ($attempt = 0; $attempt < 2; $attempt++)
        {
            // Clear any previous response headers
            $this->ResponseHeaders = [];

            if ($this->Debug || $this->LastRequestType != "GET")
            {
                Console::debug("{$this->LastRequestType} {$this->BaseUrl}{$this->LastQuery}", null, null, $depth);
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
            $this->LastCurlInfo        = curl_getinfo($this->Handle);
            $this->LastResponseHeaders = $this->ResponseHeaders;

            if (is_null($error))
            {
                $this->LastResponse     = curl_multi_getcontent($this->Handle);
                $this->LastResponseCode = (int)curl_getinfo($this->Handle, CURLINFO_RESPONSE_CODE);
            }

            curl_multi_remove_handle(self::$MultiHandle, $this->Handle);

            if (!is_null($error))
            {
                throw new CurlerException($this, "cURL error: " . curl_strerror($error));
            }

            if ($this->AutoRetryAfter &&
                $attempt == 0 &&
                $this->LastResponseCode == 429 &&
                !is_null($after = $this->getLastRetryAfter()) &&
                ($this->AutoRetryAfterMax == 0 || $after <= $this->AutoRetryAfterMax))
            {
                // Sleep for at least one second
                $after = max(1, $after);
                Console::debug("Received HTTP error 429 Too Many Requests, sleeping for {$after}s", null, null, $depth);
                sleep($after);

                continue;
            }

            break;
        }

        if ($close)
        {
            curl_close($this->Handle);
        }

        if ($this->LastResponseCode >= 400 && $this->ThrowHttpError)
        {
            throw new CurlerException($this, "HTTP error " . $this->getLastStatusLine());
        }

        return $this->LastResponse;
    }

    public function getBaseUrl(): string
    {
        return $this->BaseUrl;
    }

    public function getHeaders(): CurlerHeaders
    {
        return $this->Headers;
    }

    public function getThrowHttpError(): bool
    {
        return $this->ThrowHttpError;
    }

    public function getAutoRetryAfter(): bool
    {
        return $this->AutoRetryAfter;
    }

    public function getAutoRetryAfterMax(): int
    {
        return $this->AutoRetryAfterMax;
    }

    public function getDebug(): bool
    {
        return $this->Debug;
    }

    public function getDataAsJson(): bool
    {
        return $this->DataAsJson;
    }

    public function getForceNumericKeys(): bool
    {
        return $this->ForceNumericKeys;
    }

    public function setForceNumericKeys(bool $value)
    {
        $this->ForceNumericKeys = $value;
    }

    public function enableThrowHttpError()
    {
        $this->ThrowHttpError = true;
    }

    public function disableThrowHttpError()
    {
        $this->ThrowHttpError = false;
    }

    public function enableAutoRetryAfter()
    {
        $this->AutoRetryAfter = true;
    }

    public function disableAutoRetryAfter()
    {
        $this->AutoRetryAfter = false;
    }

    /**
     * @param int $seconds A positive integer, or `0` for no maximum.
     */
    public function setMaxRetryAfter(int $seconds)
    {
        if ($seconds < 0)
        {
            throw new UnexpectedValueException("seconds must be greater than or equal to 0");
        }

        $this->AutoRetryAfterMax = $seconds;
    }

    public function enableDebug()
    {
        $this->Debug = true;
    }

    public function disableDebug()
    {
        $this->Debug = false;
    }

    public function enableDataAsJson()
    {
        $this->DataAsJson = true;
    }

    public function disableDataAsJson()
    {
        $this->DataAsJson = false;
    }

    public function get(array $queryString = null): string
    {
        $this->initialise("GET", $queryString);

        return $this->execute();
    }

    public function getJson(array $queryString = null)
    {
        $this->InternalStackDepth = 1;

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
        $this->InternalStackDepth = 1;

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
        $this->InternalStackDepth = 1;

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
        $this->InternalStackDepth = 1;

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
        $this->InternalStackDepth = 1;

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
        $this->InternalStackDepth = 1;

        return json_decode($this->delete($data, $queryString, $dataAsJson), true);
    }

    public function getLastRetryAfter(): ?int
    {
        $retryAfter = $this->LastResponseHeaders["retry-after"] ?? null;

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

    public function getLastRequestType(): ?string
    {
        return $this->LastRequestType;
    }

    public function getLastQuery(): ?string
    {
        return $this->LastQuery;
    }

    public function getLastRequestData()
    {
        return $this->LastRequestData;
    }

    public function getLastCurlInfo(): ?array
    {
        return $this->LastCurlInfo;
    }

    public function getLastResponse(): ?string
    {
        return $this->LastResponse;
    }

    public function getLastResponseCode(): ?int
    {
        return $this->LastResponseCode;
    }

    public function getLastResponseHeaders(): ?array
    {
        return $this->LastResponseHeaders;
    }

    public function getLastStatusLine(): ?string
    {
        return $this->LastResponseHeaders["status"] ?? (string)$this->LastResponseCode;
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

            if (preg_match("/<([^>]+)>;\\s*rel=(['\"])next\\2/", $this->LastResponseHeaders["link"] ?? "", $matches))
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
                if ($this->LastResponseHeaders["odata-version"] == "4.0")
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

            $this->InternalStackDepth = 1;

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
                    $nextQuery          = [
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
