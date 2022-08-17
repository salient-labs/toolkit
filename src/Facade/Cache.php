<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\CacheStore;

/**
 * A facade for CacheStore
 *
 * @method static CacheStore load(string $filename = ':memory:', bool $autoFlush = true) Create and return the underlying CacheStore
 * @method static CacheStore getInstance() Return the underlying CacheStore
 * @method static bool isLoaded() Return true if the underlying CacheStore has been created
 * @method static CacheStore close() If a database is open, close it
 * @method static CacheStore delete(string $key) Delete an item
 * @method static CacheStore flush() Delete all items
 * @method static CacheStore flushExpired() Delete expired items
 * @method static mixed get(string $key, ?int $maxAge = null) Retrieve an item
 * @method static string|null getFilename() Get the filename of the database
 * @method static bool isOpen() Check if a database is open
 * @method static mixed maybeGet(string $key, callable $callback, int $expiry = 0) Retrieve an item, or get it from a callback and store it for next time
 * @method static CacheStore open(string $filename = ':memory:', bool $autoFlush = true) Create or open a cache database
 * @method static CacheStore set(string $key, mixed $value, int $expiry = 0) Store an item
 *
 * @uses CacheStore
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Store\CacheStore' --generate='Lkrms\Facade\Cache'
 */
final class Cache extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return CacheStore::class;
    }
}
