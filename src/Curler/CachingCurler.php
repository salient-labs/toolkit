<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Facade\Cache;
use Lkrms\Util\Generate;

/**
 * Adds GET request caching to Curler
 *
 * @property bool $CachePostRequests
 */
class CachingCurler extends Curler
{
    /**
     * @var bool
     */
    protected $CachePostRequests = false;

    /**
     * @var int
     */
    private $Expiry;

    /**
     * @var callable|null
     */
    private $Callback;

    public static function getGettable(): array
    {
        return array_merge(parent::getGettable(), [
            "CachePostRequests"
        ]);
    }

    public static function getSettable(): array
    {
        return array_merge(parent::getSettable(), [
            "CachePostRequests"
        ]);
    }

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

        if (Cache::isLoaded() && ($this->Method == "GET" ||
            ($this->CachePostRequests && $this->Method == "POST" &&
                !is_array($this->Data))))
        {
            $url     = curl_getinfo($this->Handle, CURLINFO_EFFECTIVE_URL);
            $headers = (is_null($this->Callback)
                ? $this->Headers->getPublicHeaders()
                : ($this->Callback)($this->Headers));
            if ($this->Method != "GET")
            {
                $url       = "{$this->Method}:$url";
                $headers[] = $this->Data;
            }
            $key    = "curler/$url/" . Generate::hash(...$headers);
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
