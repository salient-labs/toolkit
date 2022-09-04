<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\CacheStore;

/**
 * A facade for CacheStore
 *
 * @method static CacheStore load(string $filename = ':memory:', bool $autoFlush = true) Load and return an instance of the underlying `CacheStore` class
 * @method static CacheStore getInstance() Return the underlying `CacheStore` instance
 * @method static bool isLoaded() Return true if an underlying `CacheStore` instance has been loaded
 * @method static void unload() Clear the underlying `CacheStore` instance
 * @method static CacheStore close() If a database is open, close it (see {@see CacheStore::close()})
 * @method static CacheStore delete(string $key) Delete an item (see {@see CacheStore::delete()})
 * @method static CacheStore flush() Delete all items (see {@see CacheStore::flush()})
 * @method static CacheStore flushExpired() Delete expired items (see {@see CacheStore::flushExpired()})
 * @method static mixed get(string $key, ?int $maxAge = null) Retrieve an item (see {@see CacheStore::get()})
 * @method static string|null getFilename() Get the filename of the database (see {@see CacheStore::getFilename()})
 * @method static bool isOpen() Check if a database is open (see {@see CacheStore::isOpen()})
 * @method static mixed maybeGet(string $key, callable $callback, int $expiry = 0) Retrieve an item, or get it from a callback and store it for next time (see {@see CacheStore::maybeGet()})
 * @method static CacheStore open(string $filename = ':memory:', bool $autoFlush = true) Create or open a cache database (see {@see CacheStore::open()})
 * @method static CacheStore set(string $key, mixed $value, int $expiry = 0) Store an item (see {@see CacheStore::set()})
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
