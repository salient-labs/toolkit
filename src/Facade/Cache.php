<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Store\CacheStore;

/**
 * A facade for \Lkrms\Store\CacheStore
 *
 * @method static CacheStore load(string $filename = ':memory:') Load and return an instance of the underlying CacheStore class
 * @method static CacheStore getInstance() Get the underlying CacheStore instance
 * @method static bool isLoaded() True if an underlying CacheStore instance has been loaded
 * @method static void unload() Clear the underlying CacheStore instance
 * @method static CacheStore close() Close the database
 * @method static CacheStore delete(string $key) Delete an item (see {@see CacheStore::delete()})
 * @method static CacheStore flush() Delete all items
 * @method static CacheStore flushExpired() Delete expired items
 * @method static mixed|false get(string $key, int|null $maxAge = null) Retrieve an item (see {@see CacheStore::get()})
 * @method static string|null getFilename() Get the filename of the database
 * @method static bool has(string $key, int|null $maxAge = null) True if an item exists and has not expired (see {@see CacheStore::has()})
 * @method static bool isOpen() Check if a database is open
 * @method static mixed maybeGet(string $key, callable $callback, int $expiry = 0) Retrieve an item, or get it from a callback and store it for next time
 * @method static CacheStore open(string $filename = ':memory:') Create or open a cache database
 * @method static CacheStore set(string $key, mixed $value, int $expiry = 0) Store an item (see {@see CacheStore::set()})
 *
 * @uses CacheStore
 *
 * @extends Facade<CacheStore>
 */
final class Cache extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getServiceName(): string
    {
        return CacheStore::class;
    }
}
