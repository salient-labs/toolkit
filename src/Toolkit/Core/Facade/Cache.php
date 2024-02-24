<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Cache\CacheStore;
use Salient\Core\AbstractFacade;
use DateTimeInterface;

/**
 * A facade for CacheStore
 *
 * @method static CacheStore asOfNow(int|null $now = null) Get a copy of the store where items do not expire over time (see {@see CacheStore::asOfNow()})
 * @method static CacheStore close() Close the database
 * @method static CacheStore delete(string $key) Delete an item stored under a given key
 * @method static CacheStore deleteAll() Delete all items
 * @method static CacheStore flush() Delete expired items
 * @method static mixed|null get(string $key, ?int $maxAge = null) Retrieve an item stored under a given key (see {@see CacheStore::get()})
 * @method static string[] getAllKeys(?int $maxAge = null) Get a list of keys under which unexpired items are stored (see {@see CacheStore::getAllKeys()})
 * @method static mixed[]|null getArray(string $key, ?int $maxAge = null) Retrieve an array stored under a given key (see {@see CacheStore::getArray()})
 * @method static string|null getFilename() Get the filename of the database
 * @method static object|null getInstanceOf(string $key, class-string $class, ?int $maxAge = null) Retrieve an instance of a class stored under a given key (see {@see CacheStore::getInstanceOf()})
 * @method static int|null getInt(string $key, ?int $maxAge = null) Retrieve an integer stored under a given key (see {@see CacheStore::getInt()})
 * @method static int getItemCount(?int $maxAge = null) Get the number of unexpired items in the store (see {@see CacheStore::getItemCount()})
 * @method static string|null getString(string $key, ?int $maxAge = null) Retrieve a string stored under a given key (see {@see CacheStore::getString()})
 * @method static bool has(string $key, ?int $maxAge = null) True if an item exists and has not expired (see {@see CacheStore::has()})
 * @method static bool isOpen() Check if a database is open
 * @method static mixed maybeGet(string $key, callable(): mixed $callback, DateTimeInterface|int|null $expires = null) Retrieve an item stored under a given key, or get it from a callback and store it for subsequent retrieval (see {@see CacheStore::maybeGet()})
 * @method static CacheStore set(string $key, mixed $value, DateTimeInterface|int|null $expires = null) Store an item under a given key (see {@see CacheStore::set()})
 *
 * @extends AbstractFacade<CacheStore>
 *
 * @generated
 */
final class Cache extends AbstractFacade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return CacheStore::class;
    }
}
