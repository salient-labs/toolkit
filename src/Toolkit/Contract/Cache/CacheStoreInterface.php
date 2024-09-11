<?php declare(strict_types=1);

namespace Salient\Contract\Cache;

use Psr\SimpleCache\CacheInterface;
use DateInterval;
use DateTimeInterface;
use LogicException;

/**
 * @api
 */
interface CacheStoreInterface extends CacheInterface
{
    /**
     * Store an item under a given key
     *
     * @param string $key
     * @param mixed $value
     * @param DateTimeInterface|DateInterval|int|null $ttl The value's TTL in
     * seconds or as a {@see DateInterval}, a {@see DateTimeInterface}
     * representing its expiration time, or `null` if it should be cached
     * indefinitely.
     *
     * Providing an integer less than or equal to `0` has the same effect as
     * calling `delete($key)`.
     * @return true
     */
    public function set($key, $value, $ttl = null): bool;

    /**
     * Check if an item exists and has not expired
     *
     * @param string $key
     */
    public function has($key): bool;

    /**
     * Retrieve an item stored under a given key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed `$default` if the item has expired or doesn't exist.
     */
    public function get($key, $default = null);

    /**
     * Retrieve an instance of a class stored under a given key
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
     * Retrieve an array stored under a given key
     *
     * @param string $key
     * @param mixed[]|null $default
     * @return mixed[]|null `$default` if the item has expired, doesn't exist or
     * is not an array.
     */
    public function getArray($key, ?array $default = null): ?array;

    /**
     * Retrieve an integer stored under a given key
     *
     * @param string $key
     * @return int|null `$default` if the item has expired, doesn't exist or is
     * not an integer.
     */
    public function getInt($key, ?int $default = null): ?int;

    /**
     * Retrieve a string stored under a given key
     *
     * @param string $key
     * @return string|null `$default` if the item has expired, doesn't exist or
     * is not a string.
     */
    public function getString($key, ?string $default = null): ?string;

    /**
     * Delete an item stored under a given key
     *
     * @param string $key
     * @return true
     */
    public function delete($key): bool;

    /**
     * Delete all items
     *
     * @return true
     */
    public function clear(): bool;

    /**
     * Store items under the given keys
     *
     * @param iterable<string,mixed> $values
     * @param DateTimeInterface|DateInterval|int|null $ttl
     * @return true
     */
    public function setMultiple($values, $ttl = null): bool;

    /**
     * Retrieve items stored under the given keys
     *
     * @param iterable<string> $keys
     * @param mixed $default
     * @return iterable<string,mixed>
     */
    public function getMultiple($keys, $default = null);

    /**
     * Delete items stored under the given keys
     *
     * @param iterable<string> $keys
     * @return true
     */
    public function deleteMultiple($keys): bool;

    /**
     * Get the number of unexpired items in the store
     */
    public function getItemCount(): int;

    /**
     * Get a list of keys under which unexpired items are stored
     *
     * @return string[]
     */
    public function getItemKeys(): array;

    /**
     * Get a copy of the store where items do not expire over time
     *
     * Returns an instance where items expire relative to the time of the call
     * to {@see asOfNow()}, allowing clients to mitigate race conditions like
     * items expiring or being replaced between subsequent calls.
     *
     * Only one copy of the store can be open at a time. Copies are closed via
     * {@see close()} or by going out of scope.
     *
     * @param int|null $now If given, items expire relative to this Unix
     * timestamp instead of the time {@see asOfNow()} is called.
     * @return static
     * @throws LogicException if the store is a copy, or if another copy of the
     * store is open.
     */
    public function asOfNow(?int $now = null): CacheStoreInterface;

    /**
     * Close the store and any underlying resources
     *
     * If the store is an instance returned by {@see asOfNow()}, the original
     * instance remains open after any locks held by the copy are released.
     */
    public function close(): void;
}
