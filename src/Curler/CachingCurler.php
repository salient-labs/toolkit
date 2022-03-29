<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Cache;
use Lkrms\Generate;

/**
 * Adds GET request caching to Curler
 *
 * @package Lkrms
 */
class CachingCurler extends Curler
{
    private $Expiry;

    private $HeadersCallback;

    /**
     *
     * @param string $baseUrl
     * @param CurlerHeaders|null $headers
     * @param int $expiry
     * @param callable|null $headersCallback To replace request headers used in
     * the cache key, provide a callback that takes the return value of
     * {@see CurlerHeaders::GetHeaders()} and returns something else.
     * @return void
     */
    public function __construct(string $baseUrl, CurlerHeaders $headers = null,
        int $expiry = 3600, callable $headersCallback = null)
    {
        $this->Expiry          = $expiry;
        $this->HeadersCallback = $headersCallback;

        parent::__construct($baseUrl, $headers);
    }

    protected function Execute($close = true): string
    {
        $this->InternalStackDepth += 1;

        if ($this->LastRequestType == "GET" && Cache::IsLoaded())
        {
            $url     = curl_getinfo($this->Handle, CURLINFO_EFFECTIVE_URL);
            $headers = $this->Headers->GetHeaders();

            if (!is_null($this->HeadersCallback))
            {
                $headers = ($this->HeadersCallback)($headers);
            }

            $key    = "curler/" . $url . "/" . Generate::hash(...$headers);
            $result = Cache::Get($key);

            if ($result === false)
            {
                $result = parent::Execute($close);
                Cache::Set($key, [$this->LastResponseHeaders, $result], $this->Expiry);
            }
            else
            {
                if ($close)
                {
                    curl_close($this->Handle);
                }

                list ($this->LastResponseHeaders, $result) = $result;
            }

            return $result;
        }

        return parent::Execute($close);
    }
}

