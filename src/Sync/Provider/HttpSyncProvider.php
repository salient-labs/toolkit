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

    abstract protected function getHeaders(): ?CurlerHeaders;

    /**
     * The time, in seconds, before upstream responses expire
     *
     * Use `null` to disable response caching (the default) or `0` to cache
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
     * Used by CachingCurler when adding request headers to cache keys
     *
     * @param CurlerHeaders $headers
     * @return string[]
     * @see CachingCurler::__construct()
     */
    protected function cacheKeyCallback(CurlerHeaders $headers): array
    {
        return $headers->GetHeaders();
    }

    final public function getCurler(string $path, int $expiry = null): Curler
    {
        if (func_num_args() <= 1)
        {
            $expiry = $this->getCacheExpiry();
        }

        if (!is_null($expiry))
        {
            return new CachingCurler(
                $this->getBaseUrl() . $path,
                $this->getHeaders(),
                $expiry,
                function (CurlerHeaders $headers) { return $this->cacheKeyCallback($headers); }
            );
        }
        else
        {
            return new Curler(
                $this->getBaseUrl() . $path,
                $this->getHeaders()
            );
        }
    }
}

