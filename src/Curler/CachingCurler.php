<?php

declare(strict_types=1);

namespace Lkrms\Curler;

use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Compute;
use Lkrms\Support\HttpRequestMethod;

/**
 * Adds GET and optional POST request caching to Curler
 *
 * @property bool $CachePostRequests
 * @property-read int $Expiry
 */
class CachingCurler extends Curler
{
    /**
     * Cache eligible POST requests?
     *
     * @var bool
     */
    protected $CachePostRequests = false;

    /**
     * Seconds before each request expires
     *
     * `0` = no expiry.
     *
     * @var int
     */
    protected $Expiry;

    /**
     * @var callable|null
     */
    private $Callback;

    public static function getReadable(): array
    {
        return array_merge(parent::getReadable(), [
            "CachePostRequests",
            "Expiry",
        ]);
    }

    public static function getWritable(): array
    {
        return array_merge(parent::getWritable(), [
            "CachePostRequests",
        ]);
    }

    /**
     * @param callable|null $callback Provide a callback to use instead of
     * `$headers->getPublicHeaders()` when adding request headers to cache keys.
     * ```php
     * callback(CurlerHeaders $headers): string[]
     * ```
     * @return void
     */
    public function __construct(string $baseUrl, ?CurlerHeaders $headers = null, ?ICurlerPager $pager = null, int $expiry = 3600, ? callable $callback = null)
    {
        parent::__construct($baseUrl, $headers, $pager);

        $this->Expiry   = $expiry;
        $this->Callback = $callback;
    }

    protected function execute(bool $close = true, int $depth = 0): string
    {
        if ($this->Method == HttpRequestMethod::GET ||
            ($this->CachePostRequests &&
                $this->Method == HttpRequestMethod::POST &&
                !is_array($this->Body)))
        {
            $key = is_null($this->Callback) ? $this->Headers->getPublicHeaders() : ($this->Callback)($this->Headers);
            if ($this->Method == HttpRequestMethod::POST)
            {
                $key[] = $this->Body;
            }
            $key = self::class . ":response:{$this->Method}:" . rawurlencode(
                $this->getEffectiveUrl()
            ) . ":" . Compute::hash(...$key);
            $last = Cache::get($key, $this->Expiry);

            if ($last === false || count($last) !== 4)
            {
                parent::execute($close, $depth + 1);
                Cache::set($key, [$this->StatusCode, $this->ReasonPhrase, $this->ResponseHeaders, $this->ResponseBody], $this->Expiry);
            }
            else
            {
                if ($close)
                {
                    $this->close();
                }
                [$this->StatusCode, $this->ReasonPhrase, $this->ResponseHeaders, $this->ResponseBody] = $last;
                $this->ResponseHeadersByName = $this->ResponseHeaders->getHeaderValues(CurlerHeadersFlag::COMBINE_REPEATED);
            }

            return $this->ResponseBody;
        }

        parent::execute($close, $depth + 1);

        return $this->ResponseBody;
    }
}
