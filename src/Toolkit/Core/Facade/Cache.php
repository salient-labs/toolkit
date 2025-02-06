<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Cache\CacheStore;
use Salient\Contract\Cache\CacheInterface;
use DateInterval;
use DateTimeInterface;

/**
 * A facade for the global cache store
 *
 * @method static CacheInterface asOfNow(int|null $now = null) Get a copy of the cache where items do not expire over time (see {@see CacheInterface::asOfNow()})
 * @method static true clear() Delete all items from the cache (see {@see CacheInterface::clear()})
 * @method static void close() Close the cache and any underlying resources (see {@see CacheInterface::close()})
 * @method static true delete(string $key) Delete an item from the cache (see {@see CacheInterface::delete()})
 * @method static true deleteMultiple(iterable<string> $keys) Delete multiple items from the cache (see {@see CacheInterface::deleteMultiple()})
 * @method static mixed get(string $key, mixed $default = null) Retrieve an item from the cache (see {@see CacheInterface::get()})
 * @method static mixed[]|null getArray(string $key, mixed[]|null $default = null) Retrieve an array from the cache (see {@see CacheInterface::getArray()})
 * @method static object|null getInstanceOf(string $key, class-string $class, object|null $default = null) Retrieve an instance of a class from the cache (see {@see CacheInterface::getInstanceOf()})
 * @method static int|null getInt(string $key, int|null $default = null) Retrieve an integer from the cache (see {@see CacheInterface::getInt()})
 * @method static int getItemCount() Get the number of unexpired items in the cache
 * @method static string[] getItemKeys() Get a list of unexpired items in the cache
 * @method static iterable<string,mixed> getMultiple(iterable<string> $keys, mixed $default = null) Retrieve multiple items from the cache (see {@see CacheInterface::getMultiple()})
 * @method static string|null getString(string $key, string|null $default = null) Retrieve a string from the cache (see {@see CacheInterface::getString()})
 * @method static bool has(string $key) Check if an item is present in the cache and has not expired (see {@see CacheInterface::has()})
 * @method static true set(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null) Store an item in the cache (see {@see CacheInterface::set()})
 * @method static true setMultiple(iterable<string,mixed> $values, DateTimeInterface|DateInterval|int|null $ttl = null) Store multiple items in the cache (see {@see CacheInterface::setMultiple()})
 *
 * @api
 *
 * @extends Facade<CacheInterface>
 *
 * @generated
 */
final class Cache extends Facade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return [
            CacheInterface::class,
            CacheStore::class,
        ];
    }
}
