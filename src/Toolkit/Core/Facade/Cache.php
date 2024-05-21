<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Cache\CacheStore;
use Salient\Core\AbstractFacade;
use DateInterval;
use DateTimeInterface;

/**
 * A facade for CacheStore
 *
 * @method static CacheStore asOfNow(int|null $now = null) Get a copy of the store where items do not expire over time (see {@see CacheStore::asOfNow()})
 * @method static true clear() Delete all items (see {@see CacheStore::clear()})
 * @method static true clearExpired() Delete expired items
 * @method static CacheStore close() Close the database
 * @method static true delete(string $key) Delete an item stored under a given key (see {@see CacheStore::delete()})
 * @method static true deleteMultiple(iterable<string> $keys) Delete items stored under the given keys (see {@see CacheStore::deleteMultiple()})
 * @method static mixed get(string $key, mixed $default = null, ?int $maxAge = null) Retrieve an item stored under a given key (see {@see CacheStore::get()})
 * @method static string[] getAllKeys(?int $maxAge = null) Get a list of keys under which unexpired items are stored (see {@see CacheStore::getAllKeys()})
 * @method static mixed[]|null getArray(string $key, mixed[]|null $default = null, ?int $maxAge = null) Retrieve an array stored under a given key (see {@see CacheStore::getArray()})
 * @method static string getFilename() Get the filename of the database
 * @method static object|null getInstanceOf(string $key, class-string $class, object|null $default = null, ?int $maxAge = null) Retrieve an instance of a class stored under a given key (see {@see CacheStore::getInstanceOf()})
 * @method static int|null getInt(string $key, ?int $default = null, ?int $maxAge = null) Retrieve an integer stored under a given key (see {@see CacheStore::getInt()})
 * @method static int getItemCount(?int $maxAge = null) Get the number of unexpired items in the store (see {@see CacheStore::getItemCount()})
 * @method static iterable<string,mixed> getMultiple(iterable<string> $keys, mixed $default = null, ?int $maxAge = null) Retrieve items stored under the given keys (see {@see CacheStore::getMultiple()})
 * @method static string|null getString(string $key, ?string $default = null, ?int $maxAge = null) Retrieve a string stored under a given key (see {@see CacheStore::getString()})
 * @method static bool has(string $key, ?int $maxAge = null) Check if an item exists and has not expired (see {@see CacheStore::has()})
 * @method static bool isOpen() Check if a database is open
 * @method static true set(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null) Store an item under a given key (see {@see CacheStore::set()})
 * @method static true setMultiple(iterable<string,mixed> $values, DateTimeInterface|DateInterval|int|null $ttl = null) Store items under the given keys (see {@see CacheStore::setMultiple()})
 *
 * @api
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
