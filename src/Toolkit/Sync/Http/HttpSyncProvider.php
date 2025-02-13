<?php declare(strict_types=1);

namespace Salient\Sync\Http;

use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Curler\Exception\RequestException;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Curler\CurlerPagerInterface;
use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Contract\Sync\SyncDefinitionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Core\Exception\MethodNotImplementedException;
use Salient\Core\Facade\Cache;
use Salient\Curler\Curler;
use Salient\Http\HttpHeaders;
use Salient\Sync\Exception\UnreachableBackendException;
use Salient\Sync\AbstractSyncProvider;
use Salient\Utility\Get;

/**
 * Base class for HTTP-based RESTful API providers
 */
abstract class HttpSyncProvider extends AbstractSyncProvider
{
    /**
     * Get a Curler instance bound to an API endpoint, optionally overriding the
     * provider's default configuration
     *
     * @param int<-1,max>|null $expiry Number of seconds before cached responses
     * expire, or:
     * - `null`: do not cache responses
     * - `0`: cache responses indefinitely
     * - `-1` (default): use value returned by {@see getExpiry()}
     */
    final public function getCurler(
        string $path,
        ?int $expiry = -1,
        ?HttpHeadersInterface $headers = null,
        ?CurlerPagerInterface $pager = null,
        bool $alwaysPaginate = false,
        ?DateFormatterInterface $dateFormatter = null
    ): CurlerInterface {
        $builder = Curler::build()
            ->uri($this->getEndpointUrl($path))
            ->headers($headers ?? $this->getHeaders($path))
            ->pager($pager ?? $this->getPager($path))
            ->alwaysPaginate($pager ? $alwaysPaginate : $this->getAlwaysPaginate($path))
            ->dateFormatter($dateFormatter ?? $this->getDateFormatter());

        if ($expiry === -1) {
            $expiry = $this->getExpiry($path);
        }

        if ($expiry !== null) {
            $builder = $builder->cacheResponses()->cacheLifetime($expiry);
        }

        return $this->filterCurler($builder->build(), $path);
    }

    /**
     * Get the URL of an API endpoint
     */
    final public function getEndpointUrl(string $path): string
    {
        return $this->getBaseUrl($path) . $path;
    }

    /**
     * Get the base URL of the upstream API
     *
     * `$path` should be ignored unless the provider uses endpoint-specific base
     * URLs to connect to the API. It must not be added to the return value.
     */
    abstract protected function getBaseUrl(string $path): string;

    /**
     * Override to return HTTP headers required by the upstream API
     *
     * @codeCoverageIgnore
     */
    protected function getHeaders(string $path): ?HttpHeadersInterface
    {
        return null;
    }

    /**
     * Get a new HttpHeaders instance
     */
    final protected function headers(): HttpHeaders
    {
        return new HttpHeaders();
    }

    /**
     * Override to return a handler for paginated data from the upstream API
     *
     * @codeCoverageIgnore
     */
    protected function getPager(string $path): ?CurlerPagerInterface
    {
        return null;
    }

    /**
     * Override if the pager returned by getPager() should be used to process
     * requests even if no pagination is required
     *
     * @codeCoverageIgnore
     */
    protected function getAlwaysPaginate(string $path): bool
    {
        return false;
    }

    /**
     * Override to specify the number of seconds before cached responses from
     * the upstream API expire
     *
     * @return int<0,max>|null - `null` (default): do not cache responses
     * - `0`: cache responses indefinitely
     *
     * @codeCoverageIgnore
     */
    protected function getExpiry(string $path): ?int
    {
        return null;
    }

    /**
     * Override to customise Curler instances before they are used to perform
     * sync operations
     *
     * Values passed to {@see HttpSyncProvider::getCurler()} are applied before
     * this method is called.
     *
     * @codeCoverageIgnore
     */
    protected function filterCurler(CurlerInterface $curler, string $path): CurlerInterface
    {
        return $curler;
    }

    /**
     * @inheritDoc
     */
    final public function getDefinition(string $entity): SyncDefinitionInterface
    {
        return $this->getHttpDefinition($entity);
    }

    /**
     * Override to implement sync operations by returning an HttpSyncDefinition
     * object for the given entity
     *
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entity
     * @return HttpSyncDefinition<TEntity,$this>
     *
     * @codeCoverageIgnore
     */
    protected function getHttpDefinition(string $entity): HttpSyncDefinition
    {
        return $this->builderFor($entity)->build();
    }

    /**
     * Get a new HttpSyncDefinitionBuilder for an entity
     *
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entity
     * @return HttpSyncDefinitionBuilder<TEntity,$this>
     */
    final protected function builderFor(string $entity): HttpSyncDefinitionBuilder
    {
        return HttpSyncDefinition::build()
            ->entity($entity)
            ->provider($this);
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

        if (!Cache::has($key)) {
            try {
                $resource = $this->getHeartbeat();
                // @codeCoverageIgnoreStart
            } catch (RequestException $ex) {
                throw new UnreachableBackendException(
                    $this,
                    $ex->getMessage(),
                    $ex,
                );
                // @codeCoverageIgnoreEnd
            }
            Cache::set($key, $resource, $ttl);
        }

        return $this;
    }

    /**
     * Get a low-cost resource from the backend to confirm reachability
     *
     * @return mixed
     *
     * @codeCoverageIgnore
     */
    protected function getHeartbeat()
    {
        throw new MethodNotImplementedException(
            static::class,
            __FUNCTION__,
            HttpSyncProvider::class,
        );
    }
}
