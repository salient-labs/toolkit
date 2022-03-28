<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use CurlHandle;
use CurlMultiHandle;
use Lkrms\Console\Console;
use Lkrms\Convert;
use Lkrms\Env;
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
     * @var array
     */
    protected $ResponseHeaders;

    /**
     * @var string
     */
    protected $LastRequestType;

    /**
     * @var string
     */
    protected $LastQuery;

    /**
     * @var mixed
     */
    protected $LastRequestData;

    /**
     * @var array
     */
    protected $LastCurlInfo;

    /**
     * @var string
     */
    protected $LastResponse;

    /**
     * @var int
     */
    protected $LastResponseCode;

    /**
     * @var array
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
     * Used with calls to Console::Debug
     *
     * @var int
     */
    protected $InternalStackDepth = 0;

    /**
     * @var CurlHandle
     */
    protected $Handle;

    /**
     * @var CurlMultiHandle
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

    private function CreateHandle(string $url)
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

    protected function Initialise($requestType, ?array $queryString)
    {
        if (empty($queryString))
        {
            $query = "";
        }
        else
        {
            $query = "?" . Convert::dataToQuery($queryString, $this->ForceNumericKeys);
        }

        $this->CreateHandle($this->BaseUrl . $query);

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

        $this->Headers->UnsetHeader("Content-Type");
        $this->LastRequestType     = $requestType;
        $this->LastQuery           = $query;
        $this->LastRequestData     = null;
        $this->LastCurlInfo        = null;
        $this->LastResponse        = null;
        $this->LastResponseCode    = null;
        $this->LastResponseHeaders = null;
    }

    protected function SetData(?array $data, ?bool $asJson)
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
                        $value   = $value->GetCurlFile();
                        $hasFile = true;
                    }
                });

            if ($hasFile)
            {
                $query = $data;
            }
            elseif ($asJson)
            {
                $this->Headers->SetHeader("Content-Type", "application/json");
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

    protected function Execute($close = true): string
    {
        // Console::Debug() should print the details of whatever called a Curler
        // public method, i.e. not \Execute, not \Get, but one frame deeper
        $depth = $this->InternalStackDepth + 2;

        // Reset it now in case there's an error later
        $this->InternalStackDepth = 0;

        // Add headers for authentication etc.
        curl_setopt($this->Handle, CURLOPT_HTTPHEADER, $this->Headers->GetHeaders());

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
                Console::Debug("{$this->LastRequestType} {$this->BaseUrl}{$this->LastQuery}", null, null, $depth);
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
                !is_null($after = $this->GetLastRetryAfter()) &&
                ($this->AutoRetryAfterMax == 0 || $after <= $this->AutoRetryAfterMax))
            {
                // Sleep for at least one second
                $after = max(1, $after);
                Console::Debug("Received HTTP error 429 Too Many Requests, sleeping for {$after}s", null, null, $depth);
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
            throw new CurlerException($this, "HTTP error " . $this->GetLastStatusLine());
        }

        return $this->LastResponse;
    }

    public function GetBaseUrl(): string
    {
        return $this->BaseUrl;
    }

    public function GetHeaders(): CurlerHeaders
    {
        return $this->Headers;
    }

    public function GetThrowHttpError(): bool
    {
        return $this->ThrowHttpError;
    }

    public function GetAutoRetryAfter(): bool
    {
        return $this->AutoRetryAfter;
    }

    public function GetAutoRetryAfterMax(): int
    {
        return $this->AutoRetryAfterMax;
    }

    public function GetDebug(): bool
    {
        return $this->Debug;
    }

    public function GetDataAsJson(): bool
    {
        return $this->DataAsJson;
    }

    public function GetForceNumericKeys(): bool
    {
        return $this->ForceNumericKeys;
    }

    public function SetForceNumericKeys(bool $value)
    {
        $this->ForceNumericKeys = $value;
    }

    public function EnableThrowHttpError()
    {
        $this->ThrowHttpError = true;
    }

    public function DisableThrowHttpError()
    {
        $this->ThrowHttpError = false;
    }

    public function EnableAutoRetryAfter()
    {
        $this->AutoRetryAfter = true;
    }

    public function DisableAutoRetryAfter()
    {
        $this->AutoRetryAfter = false;
    }

    /**
     * @param int $seconds A positive integer, or `0` for no maximum.
     */
    public function SetMaxRetryAfter(int $seconds)
    {
        if ($seconds < 0)
        {
            throw new UnexpectedValueException("seconds must be greater than or equal to 0");
        }

        $this->AutoRetryAfterMax = $seconds;
    }

    public function EnableDebug()
    {
        $this->Debug = true;
    }

    public function DisableDebug()
    {
        $this->Debug = false;
    }

    public function EnableDataAsJson()
    {
        $this->DataAsJson = true;
    }

    public function DisableDataAsJson()
    {
        $this->DataAsJson = false;
    }

    public function Get(array $queryString = null): string
    {
        $this->Initialise("GET", $queryString);

        return $this->Execute();
    }

    public function GetJson(array $queryString = null)
    {
        $this->InternalStackDepth = 1;

        return json_decode($this->Get($queryString), true);
    }

    public function Post(array $data = null, array $queryString = null, bool $dataAsJson = null): string
    {
        $this->Initialise("POST", $queryString);
        $this->SetData($data, $dataAsJson);

        return $this->Execute();
    }

    public function PostJson(array $data = null, array $queryString = null, bool $dataAsJson = null)
    {
        $this->InternalStackDepth = 1;

        return json_decode($this->Post($data, $queryString, $dataAsJson), true);
    }

    public function RawPost(string $data, string $contentType, array $queryString = null): string
    {
        $this->Initialise("POST", $queryString);
        $this->Headers->SetHeader("Content-Type", $contentType);
        curl_setopt($this->Handle, CURLOPT_POSTFIELDS, $data);

        return $this->Execute();
    }

    public function RawPostJson(string $data, string $contentType, array $queryString = null)
    {
        $this->InternalStackDepth = 1;

        return json_decode($this->RawPost($data, $contentType, $queryString), true);
    }

    public function Put(array $data = null, array $queryString = null, bool $dataAsJson = null): string
    {
        $this->Initialise("PUT", $queryString);
        $this->SetData($data, $dataAsJson);

        return $this->Execute();
    }

    public function PutJson(array $data = null, array $queryString = null, bool $dataAsJson = null)
    {
        $this->InternalStackDepth = 1;

        return json_decode($this->Put($data, $queryString, $dataAsJson), true);
    }

    public function Patch(array $data = null, array $queryString = null, bool $dataAsJson = null): string
    {
        $this->Initialise("PATCH", $queryString);
        $this->SetData($data, $dataAsJson);

        return $this->Execute();
    }

    public function PatchJson(array $data = null, array $queryString = null, bool $dataAsJson = null)
    {
        $this->InternalStackDepth = 1;

        return json_decode($this->Patch($data, $queryString, $dataAsJson), true);
    }

    public function Delete(array $data = null, array $queryString = null, bool $dataAsJson = null): string
    {
        $this->Initialise("DELETE", $queryString);
        $this->SetData($data, $dataAsJson);

        return $this->Execute();
    }

    public function DeleteJson(array $data = null, array $queryString = null, bool $dataAsJson = null)
    {
        $this->InternalStackDepth = 1;

        return json_decode($this->Delete($data, $queryString, $dataAsJson), true);
    }

    public function GetLastRetryAfter(): ?int
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

    public function GetLastRequestType(): ?string
    {
        return $this->LastRequestType;
    }

    public function GetLastQuery(): ?string
    {
        return $this->LastQuery;
    }

    public function GetLastRequestData()
    {
        return $this->LastRequestData;
    }

    public function GetLastCurlInfo(): ?array
    {
        return $this->LastCurlInfo;
    }

    public function GetLastResponse(): ?string
    {
        return $this->LastResponse;
    }

    public function GetLastResponseCode(): ?int
    {
        return $this->LastResponseCode;
    }

    public function GetLastResponseHeaders(): ?array
    {
        return $this->LastResponseHeaders;
    }

    public function GetLastStatusLine(): ?string
    {
        return $this->LastResponseHeaders["status"] ?? (string)$this->LastResponseCode;
    }

    /**
     * Follow HTTP `Link` headers to retrieve and merge paged JSON data
     *
     * @param array $queryString
     * @return array All returned entities.
     */
    public function GetAllLinked(array $queryString = null): array
    {
        $this->Initialise("GET", $queryString);
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
            $result   = json_decode($this->Execute(false), true);
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
    public function GetAllLinkedByEntity($entityName, array $queryString = null): array
    {
        $this->Initialise("GET", $queryString);
        $entities = [];
        $nextUrl  = null;

        do
        {
            if ($nextUrl)
            {
                curl_setopt($this->Handle, CURLOPT_URL, $nextUrl);
            }

            // Collect data from response and move on to next page
            $result   = json_decode($this->Execute(false), true);
            $entities = array_merge($entities, $result[$entityName]);
            $nextUrl  = $result["links"]["next"] ?? null;
        }
        while ($nextUrl);

        curl_close($this->Handle);

        return $entities;
    }

    public function GetAllLinkedByOData(array $queryString = null, string $prefix = null)
    {
        $this->Initialise("GET", $queryString);
        $entities = [];
        $nextUrl  = null;

        do
        {
            if ($nextUrl)
            {
                curl_setopt($this->Handle, CURLOPT_URL, $nextUrl);
            }

            // Collect data from response and move on to next page
            $result = json_decode($this->Execute(false), true);

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

    private static function CollateNested($data, array $path, array & $entities)
    {
        if (empty($path))
        {
            $entities = array_merge($entities, Convert::toList($data));
        }
        elseif (Test::isListArray($data))
        {
            foreach ($data as $nested)
            {
                self::CollateNested($nested, $path, $entities);
            }
        }
        else
        {
            $field = array_shift($path);

            // Gracefully skip missing data
            if (isset($data[$field]))
            {
                self::CollateNested($data[$field], $path, $entities);
            }
        }
    }

    public static function WalkGraphQL(array & $data, callable $filter = null)
    {
        if (Test::isListArray($data))
        {
            array_walk($data, function (&$data) use ($filter)
            {
                if (is_array($data))
                {
                    self::WalkGraphQL($data, $filter);
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
                self::WalkGraphQL($data[$key], $filter);
            }
        }
    }

    public function GetByGraphQL(
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

            $result = json_decode($this->Post($nextQuery), true);

            if (!isset($result["data"]))
            {
                throw new CurlerException($this, "No data returned");
            }

            $nextQuery = null;
            $objects   = [];
            self::CollateNested($result["data"], is_null($entityPath) ? null : explode(".", $entityPath), $objects);

            self::WalkGraphQL($objects, $filter);

            $entities = array_merge($entities, $objects);

            if (!is_null($pagePath))
            {
                $page = [];
                self::CollateNested($result["data"], explode(".", $pagePath), $page);

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

