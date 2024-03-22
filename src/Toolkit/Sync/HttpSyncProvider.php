<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Cache\CacheStore;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Sync\SyncDefinitionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Core\Exception\MethodNotImplementedException;
use Salient\Core\Facade\Cache;
use Salient\Core\Utility\Get;
use Salient\Curler\Contract\ICurlerPager;
use Salient\Curler\Exception\CurlerCurlErrorException;
use Salient\Curler\Curler;
use Salient\Curler\CurlerBuilder;
use Salient\Http\HttpHeaders;
use Salient\Sync\Exception\SyncProviderBackendUnreachableException;

/**
 * Base class for HTTP-based RESTful API providers
 */
abstract class HttpSyncProvider extends AbstractSyncProvider
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
        ?HttpHeadersInterface $headers = null,
        ?ICurlerPager $pager = null,
        ?DateFormatterInterface $dateFormatter = null
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
     * @template T of SyncEntityInterface
     *
     * @param class-string<T> $entity
     * @return SyncDefinitionInterface<T,static>
     */
    final public function getDefinition(string $entity): SyncDefinitionInterface
    {
        /** @var SyncDefinitionInterface<T,static> */
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
            Get::hash(implode("\0", $this->getBackendIdentifier())),
        ]);

        if (Cache::get($key, $ttl) === null) {
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
     * Get a new HttpHeaders instance
     */
    final protected function headers(): HttpHeaders
    {
        return new HttpHeaders();
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
     * @template TEntity of SyncEntityInterface
     * @template TProvider of HttpSyncProvider
     *
     * @param class-string<TEntity> $entity
     * @param HttpSyncDefinitionBuilder<TEntity,TProvider> $defB A definition
     * builder with `entity()` and `provider()` already applied.
     * @return HttpSyncDefinitionBuilder<TEntity,TProvider>
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
    protected function getHeaders(?string $path): ?HttpHeadersInterface
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
     * @see CacheStore::set() for more information about `$expiry` values
     */
    protected function getExpiry(?string $path): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    abstract protected function getDateFormatter(?string $path = null): DateFormatterInterface;
}
