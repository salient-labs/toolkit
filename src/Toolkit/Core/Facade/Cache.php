<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Cache\CacheStore;
use Salient\Contract\Cache\CacheStoreInterface;
use Salient\Core\AbstractFacade;
use DateInterval;
use DateTimeInterface;

/**
 * A facade for the global cache store
 *
 * @method static CacheStoreInterface asOfNow(int|null $now = null) Get a copy of the store where items do not expire over time (see {@see CacheStoreInterface::asOfNow()})
 * @method static true clear() Delete all items (see {@see CacheStoreInterface::clear()})
 * @method static void close() Close the store and any underlying resources (see {@see CacheStoreInterface::close()})
 * @method static true delete(string $key) Delete an item stored under a given key (see {@see CacheStoreInterface::delete()})
 * @method static true deleteMultiple(iterable<string> $keys) Delete items stored under the given keys (see {@see CacheStoreInterface::deleteMultiple()})
 * @method static mixed get(string $key, mixed $default = null, int|null $maxAge = null) Retrieve an item stored under a given key (see {@see CacheStoreInterface::get()})
 * @method static string[] getAllKeys(int|null $maxAge = null) Get a list of keys under which unexpired items are stored (see {@see CacheStoreInterface::getAllKeys()})
 * @method static mixed[]|null getArray(string $key, mixed[]|null $default = null, int|null $maxAge = null) Retrieve an array stored under a given key (see {@see CacheStoreInterface::getArray()})
 * @method static object|null getInstanceOf(string $key, class-string $class, object|null $default = null, int|null $maxAge = null) Retrieve an instance of a class stored under a given key (see {@see CacheStoreInterface::getInstanceOf()})
 * @method static int|null getInt(string $key, int|null $default = null, int|null $maxAge = null) Retrieve an integer stored under a given key (see {@see CacheStoreInterface::getInt()})
 * @method static int getItemCount(int|null $maxAge = null) Get the number of unexpired items in the store (see {@see CacheStoreInterface::getItemCount()})
 * @method static iterable<string,mixed> getMultiple(iterable<string> $keys, mixed $default = null, int|null $maxAge = null) Retrieve items stored under the given keys (see {@see CacheStoreInterface::getMultiple()})
 * @method static string|null getString(string $key, string|null $default = null, int|null $maxAge = null) Retrieve a string stored under a given key (see {@see CacheStoreInterface::getString()})
 * @method static bool has(string $key, int|null $maxAge = null) Check if an item exists and has not expired (see {@see CacheStoreInterface::has()})
 * @method static true set(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null) Store an item under a given key (see {@see CacheStoreInterface::set()})
 * @method static true setMultiple(iterable<string,mixed> $values, DateTimeInterface|DateInterval|int|null $ttl = null) Store items under the given keys (see {@see CacheStoreInterface::setMultiple()})
 *
 * @api
 *
 * @extends AbstractFacade<CacheStoreInterface>
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
        return [
            CacheStoreInterface::class => CacheStore::class,
        ];
    }
}
