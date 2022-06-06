<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use Lkrms\Curler\CachingCurler;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;

/**
 * Base class for HTTP-based RESTful API providers
 *
 */
abstract class HttpSyncProvider extends SyncProvider
{
    /**
     * Return the base URL of the upstream API
     *
     * `$path` should be ignored unless the provider uses endpoint-specific base
     * URLs to connect to the API. It should never be added to the return value.
     *
     * @param null|string $path The endpoint requested via
     * {@see HttpSyncProvider::getCurler()}.
     * @return string
     */
    abstract protected function getBaseUrl(?string $path): string;

    /**
     * Return headers to use when connecting to the upstream API
     *
     * Called once per {@see HttpSyncProvider::getCurler()} call.
     *
     * @param null|string $path The endpoint requested via
     * {@see HttpSyncProvider::getCurler()}.
     * @return null|CurlerHeaders
     */
    abstract protected function getHeaders(?string $path): ?CurlerHeaders;

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
     * @see \Lkrms\Store\CacheStore::set() for more information about `$expiry`
     * values
     */
    protected function getCacheExpiry(): ?int
    {
        return null;
    }

    /**
     * Prepare a Curler instance for connecting to the upstream API
     *
     * Called once per {@see HttpSyncProvider::getCurler()} call.
     *
     * @param Curler $curler
     */
    protected function prepareCurler(Curler $curler): void
    {
    }

    /**
     * Used by CachingCurler when adding request headers to cache keys
     *
     * @param CurlerHeaders $headers
     * @return string[]
     * @see CachingCurler::__construct()
     */
    protected function getCurlerCacheKey(CurlerHeaders $headers): array
    {
        return $headers->getPublicHeaders();
    }

    /**
     * Get the URL of an API endpoint
     *
     * @param string $path
     * @return string
     */
    final public function getEndpointUrl(string $path): string
    {
        return $this->getBaseUrl($path) . $path;
    }

    /**
     * Get a Curler or CachingCurler instance bound to an API endpoint
     *
     * @param string $path
     * @param int|null $expiry
     * @return Curler
     */
    final public function getCurler(string $path, int $expiry = null): Curler
    {
        if (func_num_args() < 2)
        {
            $expiry = $this->getCacheExpiry();
        }

        if (!is_null($expiry))
        {
            $curler = new CachingCurler(
                $this->getEndpointUrl($path),
                $this->getHeaders($path),
                $expiry,
                fn(CurlerHeaders $headers) => $this->getCurlerCacheKey($headers)
            );
        }
        else
        {
            $curler = new Curler(
                $this->getEndpointUrl($path),
                $this->getHeaders($path)
            );
        }

        $this->prepareCurler($curler);

        return $curler;
    }
}
