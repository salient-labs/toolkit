<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Store\Cache;
use Lkrms\Util\Generate;

/**
 * Adds GET request caching to Curler
 *
 */
class CachingCurler extends Curler
{
    private $Expiry;

    private $Callback;

    /**
     *
     * @param string $baseUrl
     * @param CurlerHeaders|null $headers
     * @param int $expiry
     * @param callable|null $callback Provide a callback to use instead of
     * `$headers->getPublicHeaders()` when adding request headers to cache keys.
     * ```php
     * callback(CurlerHeaders $headers): string[]
     * ```
     * @return void
     */
    public function __construct(string $baseUrl, CurlerHeaders $headers = null,
        int $expiry = 3600, callable $callback = null)
    {
        $this->Expiry   = $expiry;
        $this->Callback = $callback;

        parent::__construct($baseUrl, $headers);
    }

    protected function execute($close = true): string
    {
        $this->StackDepth += 1;

        if ($this->Method == "GET" && Cache::isLoaded())
        {
            $url     = curl_getinfo($this->Handle, CURLINFO_EFFECTIVE_URL);
            $headers = (is_null($this->Callback)
                ? $this->Headers->getPublicHeaders()
                : ($this->Callback)($this->Headers));
            $key    = "curler/" . $url . "/" . Generate::hash(...$headers);
            $result = Cache::get($key);

            if ($result === false)
            {
                $result = parent::execute($close);
                Cache::set($key, [$this->ResponseHeadersByName, $result], $this->Expiry);
            }
            else
            {
                if ($close)
                {
                    curl_close($this->Handle);
                }

                list ($this->ResponseHeadersByName, $result) = $result;
            }

            return $result;
        }

        return parent::execute($close);
    }
}
