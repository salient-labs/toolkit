<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Lkrms\Curler\CachingCurler;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Sync\SyncProvider;

/**
 * Base class for HTTP-based RESTful API providers
 *
 * @package Lkrms
 */
abstract class HttpSyncProvider extends SyncProvider
{
    abstract protected function getBaseUrl(): string;

    /**
     * Headers to use when creating a Curler instance
     *
     * Called once per {@see HttpSyncProvider::getCurler()} call.
     *
     * The requested endpoint is passed as the first parameter.
     *
     * @return null|CurlerHeaders
     */
    abstract protected function getHeaders(): ?CurlerHeaders;

    /**
     * The time, in seconds, before upstream responses expire
     *
     * Return `null` to disable response caching (the default) or `0` to cache
     * upstream responses indefinitely.
     *
     * The `$expiry` parameter of {@see HttpSyncProvider::getCurler()} takes
     * precedence.
     *
     * @return null|int
     * @see \Lkrms\Cache::set() for more information about `$expiry` values
     */
    protected function getCacheExpiry(): ?int
    {
        return null;
    }

    /**
     * Automatically retry rate-limited requests with Retry-After headers?
     *
     * @return bool
     */
    protected function getAutoRetryAfter(): bool
    {
        return false;
    }

    /**
     * Used by CachingCurler when adding request headers to cache keys
     *
     * @param CurlerHeaders $headers
     * @return string[]
     * @see CachingCurler::__construct()
     */
    protected function cacheKeyCallback(CurlerHeaders $headers): array
    {
        return $headers->getHeaders();
    }

    final public function getCurler(string $path, int $expiry = null): Curler
    {
        if (func_num_args() <= 1)
        {
            $expiry = $this->getCacheExpiry();
        }

        if (!is_null($expiry))
        {
            $curler = new CachingCurler(
                $this->getBaseUrl() . $path,
                $this->getHeaders($path),
                $expiry,
                function (CurlerHeaders $headers) { return $this->cacheKeyCallback($headers); }
            );
        }
        else
        {
            $curler = new Curler(
                $this->getBaseUrl() . $path,
                $this->getHeaders($path)
            );
        }

        if ($this->getAutoRetryAfter())
        {
            $curler->enableAutoRetryAfter();
        }

        return $curler;
    }
}
