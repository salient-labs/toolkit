<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Contract\IProvider;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;

/**
 * Base class for HTTP-based RESTful API providers
 *
 */
abstract class HttpSyncProvider extends SyncProvider
{
    /**
     * Get the base URL of the upstream API
     *
     * `$path` should be ignored unless the provider uses endpoint-specific base
     * URLs to connect to the API. It should never be added to the return value.
     *
     */
    abstract protected function getBaseUrl(?string $path): string;

    /**
     * Get HTTP headers required by the upstream API
     *
     */
    protected function getHeaders(?string $path): ?ICurlerHeaders
    {
        return null;
    }

    /**
     * Get a handler for paginated data from the upstream API
     *
     */
    protected function getPager(?string $path): ?ICurlerPager
    {
        return null;
    }

    /**
     * The time, in seconds, before upstream API responses expire
     *
     * Return `null` to disable response caching (the default) or `0` to cache
     * upstream responses indefinitely.
     *
     * Called when {@see HttpSyncProvider::getCurler()} is called with a
     * negative `$expiry`.
     *
     * @see \Lkrms\Store\CacheStore::set() for more information about `$expiry`
     * values
     */
    protected function getExpiry(?string $path): ?int
    {
        return null;
    }

    /**
     * Configure an unresolved Curler instance for upstream requests
     *
     * Called once per {@see HttpSyncProvider::getCurler()} call.
     *
     * {@see HttpSyncProvider::getHeaders()} and
     * {@see HttpSyncProvider::getPager()} are not called if
     * {@see HttpSyncProvider::buildCurler()} sets their respective properties
     * via {@see CurlerBuilder::headers()} or {@see CurlerBuilder::pager()}.
     *
     */
    protected function buildCurler(CurlerBuilder $curlerB): CurlerBuilder
    {
        return $curlerB;
    }

    /**
     * Surface the provider's implementation of sync operations for an entity
     * via an HttpSyncDefinition object
     *
     * Return `$defB` if no sync operations are implemented for the entity.
     *
     * @param HttpSyncDefinitionBuilder $defB A definition builder with
     * `entity()` and `provider()` already applied.
     */
    protected function getHttpDefinition(string $entity, HttpSyncDefinitionBuilder $defB): HttpSyncDefinitionBuilder
    {
        return $defB;
    }

    final public function getDefinition(string $entity): ISyncDefinition
    {
        return
            $this->getHttpDefinition(
                     $entity,
                     HttpSyncDefinition::build()
                         ->entity($entity)
                         ->provider($this)
                 )
                 ->go();
    }

    /**
     * Get the URL of an API endpoint
     *
     */
    final public function getEndpointUrl(string $path): string
    {
        return $this->getBaseUrl($path) . $path;
    }

    /**
     * Get a Curler instance bound to an API endpoint
     *
     * If `$expiry` is an integer less than `0`, the return value of
     * {@see HttpSyncProvider::getExpiry()} is used as the response expiry time.
     *
     */
    final public function getCurler(string $path, ?int $expiry = -1, ?ICurlerHeaders $headers = null, ?ICurlerPager $pager = null): Curler
    {
        $curlerB = $this->buildCurler(CurlerBuilder::build());

        if (!is_null($expiry) && $expiry < 0) {
            $expiry = $this->getExpiry($path);
        }
        if (!is_null($expiry)) {
            $curlerB = $curlerB->cacheResponse()
                               ->expiry($expiry);
        } else {
            $curlerB = $curlerB->cacheResponse(false);
        }

        if ($headers) {
            $curlerB = $curlerB->headers($headers);
        } elseif (!$curlerB->isset('headers')) {
            $curlerB = $curlerB->headers($this->getHeaders($path));
        }

        if ($pager) {
            $curlerB = $curlerB->pager($pager);
        } elseif (!$curlerB->isset('pager')) {
            $curlerB = $curlerB->pager($this->getPager($path));
        }

        return
            $curlerB->baseUrl($this->getEndpointUrl($path))
                    ->go();
    }

    public function checkHeartbeat(int $ttl = 300)
    {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            IProvider::class
        );
    }

    /**
     * @internal Override {@see HttpSyncProvider::getHeaders()} instead
     */
    final protected function getCurlerHeaders(): void {}

    /**
     * @internal Override {@see HttpSyncProvider::getPager()} instead
     */
    final protected function getCurlerPager(): void {}

    /**
     * @internal Override {@see HttpSyncProvider::getExpiry()} instead
     */
    final protected function getCurlerCacheExpiry(): void {}

    /**
     * @internal Override {@see HttpSyncProvider::buildCurler()} instead
     */
    final protected function getCurlerCacheKey(): void {}

    /**
     * @internal Override {@see HttpSyncProvider::buildCurler()} instead
     */
    final protected function prepareCurler(): void {}
}
