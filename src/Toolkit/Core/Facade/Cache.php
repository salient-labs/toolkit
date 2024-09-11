<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Cache\CacheStore;
use Salient\Contract\Cache\CacheInterface;
use Salient\Core\AbstractFacade;
use DateInterval;
use DateTimeInterface;

/**
 * A facade for the global cache store
 *
 * @method static CacheInterface asOfNow(int|null $now = null) Get a copy of the store where items do not expire over time (see {@see CacheInterface::asOfNow()})
 * @method static true clear() Delete all items (see {@see CacheInterface::clear()})
 * @method static void close() Close the store and any underlying resources (see {@see CacheInterface::close()})
 * @method static true delete(string $key) Delete an item stored under a given key (see {@see CacheInterface::delete()})
 * @method static true deleteMultiple(iterable<string> $keys) Delete items stored under the given keys (see {@see CacheInterface::deleteMultiple()})
 * @method static mixed get(string $key, mixed $default = null) Retrieve an item stored under a given key (see {@see CacheInterface::get()})
 * @method static mixed[]|null getArray(string $key, mixed[]|null $default = null) Retrieve an array stored under a given key (see {@see CacheInterface::getArray()})
 * @method static object|null getInstanceOf(string $key, class-string $class, object|null $default = null) Retrieve an instance of a class stored under a given key (see {@see CacheInterface::getInstanceOf()})
 * @method static int|null getInt(string $key, int|null $default = null) Retrieve an integer stored under a given key (see {@see CacheInterface::getInt()})
 * @method static int getItemCount() Get the number of unexpired items in the store
 * @method static string[] getItemKeys() Get a list of keys under which unexpired items are stored
 * @method static iterable<string,mixed> getMultiple(iterable<string> $keys, mixed $default = null) Retrieve items stored under the given keys (see {@see CacheInterface::getMultiple()})
 * @method static string|null getString(string $key, string|null $default = null) Retrieve a string stored under a given key (see {@see CacheInterface::getString()})
 * @method static bool has(string $key) Check if an item exists and has not expired (see {@see CacheInterface::has()})
 * @method static true set(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null) Store an item under a given key (see {@see CacheInterface::set()})
 * @method static true setMultiple(iterable<string,mixed> $values, DateTimeInterface|DateInterval|int|null $ttl = null) Store items under the given keys (see {@see CacheInterface::setMultiple()})
 *
 * @api
 *
 * @extends AbstractFacade<CacheInterface>
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
            CacheInterface::class => CacheStore::class,
        ];
    }
}
