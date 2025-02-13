<?php declare(strict_types=1);

namespace Salient\Contract\Cache;

use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Salient\Contract\Core\Instantiable;
use DateInterval;
use DateTimeInterface;

/**
 * @api
 */
interface CacheInterface extends PsrCacheInterface, Instantiable
{
    /**
     * Store an item in the cache
     *
     * @param string $key
     * @param mixed $value
     * @param DateTimeInterface|DateInterval|int|null $ttl The value's TTL in
     * seconds or as a {@see DateInterval}, a {@see DateTimeInterface}
     * representing its expiration time, or `null` if it should be cached
     * indefinitely.
     *
     * Providing a TTL less than or equal to 0 seconds has the same effect as
     * calling `delete($key)`.
     * @return true
     */
    public function set($key, $value, $ttl = null): bool;

    /**
     * Check if an item is present in the cache and has not expired
     *
     * @param string $key
     */
    public function has($key): bool;

    /**
     * Retrieve an item from the cache
     *
     * @param string $key
     * @param mixed $default
     * @return mixed `$default` if the item has expired or doesn't exist.
     */
    public function get($key, $default = null);

    /**
     * Retrieve an instance of a class from the cache
     *
     * @template T of object
     *
     * @param string $key
     * @param class-string<T> $class
     * @param T|null $default
     * @return T|null `$default` if the item has expired, doesn't exist or is
     * not an instance of `$class`.
     */
    public function getInstanceOf($key, string $class, ?object $default = null): ?object;

    /**
     * Retrieve an array from the cache
     *
     * @param string $key
     * @param mixed[]|null $default
     * @return mixed[]|null `$default` if the item has expired, doesn't exist or
     * is not an array.
     */
    public function getArray($key, ?array $default = null): ?array;

    /**
     * Retrieve an integer from the cache
     *
     * @param string $key
     * @return int|null `$default` if the item has expired, doesn't exist or is
     * not an integer.
     */
    public function getInt($key, ?int $default = null): ?int;

    /**
     * Retrieve a string from the cache
     *
     * @param string $key
     * @return string|null `$default` if the item has expired, doesn't exist or
     * is not a string.
     */
    public function getString($key, ?string $default = null): ?string;

    /**
     * Delete an item from the cache
     *
     * @param string $key
     * @return true
     */
    public function delete($key): bool;

    /**
     * Delete all items from the cache
     *
     * @return true
     */
    public function clear(): bool;

    /**
     * Store multiple items in the cache
     *
     * @param iterable<string,mixed> $values
     * @param DateTimeInterface|DateInterval|int|null $ttl
     * @return true
     */
    public function setMultiple($values, $ttl = null): bool;

    /**
     * Retrieve multiple items from the cache
     *
     * @param iterable<string> $keys
     * @param mixed $default
     * @return iterable<string,mixed>
     */
    public function getMultiple($keys, $default = null);

    /**
     * Delete multiple items from the cache
     *
     * @param iterable<string> $keys
     * @return true
     */
    public function deleteMultiple($keys): bool;

    /**
     * Get the number of unexpired items in the cache
     */
    public function getItemCount(): int;

    /**
     * Get a list of unexpired items in the cache
     *
     * @return string[]
     */
    public function getItemKeys(): array;

    /**
     * Get a copy of the cache where items do not expire over time
     *
     * Returns an instance where items expire relative to the time of the call
     * to the method, allowing clients to mitigate race conditions like items
     * expiring or being replaced between subsequent calls.
     *
     * Only one copy of the cache can be open at a time. Copies are closed via
     * {@see close()} or by going out of scope.
     *
     * @param int|null $now If given, items expire relative to this Unix
     * timestamp instead of the time the method is called.
     * @return static
     * @throws CacheCopyFailedException if the cache is an instance returned by
     * {@see asOfNow()}, or if another copy of the cache is open.
     */
    public function asOfNow(?int $now = null): self;

    /**
     * Close the cache and any underlying resources
     *
     * If the cache is an instance returned by {@see asOfNow()}, the original
     * instance remains open after any locks held by the copy are released.
     */
    public function close(): void;
}
