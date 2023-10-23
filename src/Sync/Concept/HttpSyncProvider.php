<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Contract\IDateFormatter;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Exception\CurlerCurlErrorException;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Compute;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncProviderBackendUnreachableException;
use Lkrms\Sync\Support\HttpSyncDefinition;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;

/**
 * Base class for HTTP-based RESTful API providers
 */
abstract class HttpSyncProvider extends SyncProvider
{
    /**
     * Get a Curler instance bound to an API endpoint
     *
     * If `$expiry` is an integer less than `0` (the default), it is replaced
     * with the return value of {@see HttpSyncProvider::getExpiry()}.
     *
     * If set, `$headers` and `$pager` take precedence over values applied via
     * {@see HttpSyncProvider::buildCurler()}, which, in turn, take precedence
     * over {@see HttpSyncProvider::getHeaders()} and
     * {@see HttpSyncProvider::getPager()}.
     */
    final public function getCurler(
        string $path,
        ?int $expiry = -1,
        ?ICurlerHeaders $headers = null,
        ?ICurlerPager $pager = null,
        ?IDateFormatter $dateFormatter = null
    ): Curler {
        $curlerB = $this->buildCurler(
            CurlerBuilder::build()
                ->baseUrl($this->getEndpointUrl($path))
        );

        if ($expiry !== null && $expiry < 0) {
            $expiry = $this->getExpiry($path);
        }
        if ($expiry !== null) {
            $curlerB = $curlerB->cacheResponse()->expiry($expiry);
        } else {
            $curlerB = $curlerB->cacheResponse(false);
        }

        if ($headers) {
            $curlerB = $curlerB->headers($headers);
        } elseif (!$curlerB->issetB('headers')) {
            $curlerB = $curlerB->headers($this->getHeaders($path));
        }

        if ($pager) {
            $curlerB = $curlerB->pager($pager);
        } elseif (!$curlerB->issetB('pager')) {
            $curlerB = $curlerB->pager($this->getPager($path));
        }

        if ($dateFormatter) {
            $curlerB = $curlerB->dateFormatter($dateFormatter);
        } elseif (!$curlerB->issetB('dateFormatter')) {
            $curlerB = $curlerB->dateFormatter($this->getDateFormatter($path));
        }

        return $curlerB->go();
    }

    /**
     * @inheritDoc
     *
     * @template T of ISyncEntity
     * @param class-string<T> $entity
     * @return ISyncDefinition<T,static>
     */
    final public function getDefinition(string $entity): ISyncDefinition
    {
        /** @var ISyncDefinition<T,static> */
        $def = $this
            ->buildHttpDefinition(
                $entity,
                HttpSyncDefinition::build()
                    ->entity($entity)
                    ->provider($this)
            )
            ->go();

        return $def;
    }

    /**
     * Get the URL of an API endpoint
     */
    final public function getEndpointUrl(string $path): string
    {
        return $this->getBaseUrl($path) . $path;
    }

    /**
     * @inheritDoc
     */
    final public function checkHeartbeat(int $ttl = 300)
    {
        $key = implode(':', [
            static::class,
            __FUNCTION__,
            Compute::hash(...$this->getBackendIdentifier()),
        ]);

        if (Cache::get($key, $ttl) === false) {
            try {
                $resource = $this->getHeartbeat();
            } catch (CurlerCurlErrorException $ex) {
                throw new SyncProviderBackendUnreachableException(
                    $ex->getMessage(),
                    $this,
                    $ex,
                );
            }
            Cache::set($key, $resource, $ttl);
        }

        return $this;
    }

    /**
     * Get a low-cost resource from the backend to confirm reachability
     *
     * @return mixed
     */
    protected function getHeartbeat()
    {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            HttpSyncProvider::class,
        );
    }

    /**
     * Get a new CurlerHeaders instance
     */
    final protected function headers(): CurlerHeaders
    {
        return CurlerHeaders::create();
    }

    /**
     * Configure an unresolved Curler instance for upstream requests
     *
     * `baseUrl()` has already been applied to {@see CurlerBuilder} instances
     * passed to this method.
     *
     * Called once per {@see HttpSyncProvider::getCurler()} call.
     *
     * {@see HttpSyncProvider::getHeaders()} and
     * {@see HttpSyncProvider::getPager()} are not called if
     * {@see HttpSyncProvider::buildCurler()} sets their respective properties
     * via the relevant {@see CurlerBuilder} methods. Values passed to
     * {@see HttpSyncProvider::getCurler()}'s `$headers` and `$pager` arguments
     * take precedence over all of these.
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
     * @template T of ISyncEntity
     * @param class-string<T> $entity
     * @param HttpSyncDefinitionBuilder<T,static> $defB A definition builder
     * with `entity()` and `provider()` already applied.
     * @return HttpSyncDefinitionBuilder<T,static>
     */
    protected function buildHttpDefinition(string $entity, HttpSyncDefinitionBuilder $defB): HttpSyncDefinitionBuilder
    {
        return $defB;
    }

    /**
     * Get the base URL of the upstream API
     *
     * `$path` should be ignored unless the provider uses endpoint-specific base
     * URLs to connect to the API. It should never be added to the return value.
     */
    abstract protected function getBaseUrl(?string $path): string;

    /**
     * Get HTTP headers required by the upstream API
     */
    protected function getHeaders(?string $path): ?ICurlerHeaders
    {
        return null;
    }

    /**
     * Get a handler for paginated data from the upstream API
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
     * @inheritDoc
     */
    abstract protected function getDateFormatter(?string $path = null): IDateFormatter;
}
