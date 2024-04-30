<?php declare(strict_types=1);

namespace Salient\Cache;

use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\LogicException;
use Salient\Core\AbstractStore;
use DateTimeInterface;
use SQLite3Result;
use SQLite3Stmt;

/**
 * A SQLite-backed key-value store
 *
 * Expired items are not implicitly flushed. {@see CacheStore::flush()} must be
 * called explicitly, e.g. on a schedule or once per run.
 */
final class CacheStore extends AbstractStore
{
    private ?int $Now = null;

    /**
     * Creates a new CacheStore object
     */
    public function __construct(string $filename = ':memory:')
    {
        $this
            ->requireUpsert()
            ->openDb(
                $filename,
                <<<SQL
CREATE TABLE IF NOT EXISTS
  _cache_item (
    item_key TEXT NOT NULL PRIMARY KEY,
    item_value BLOB,
    expires_at DATETIME,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    set_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) WITHOUT ROWID;

CREATE TRIGGER IF NOT EXISTS _cache_item_update AFTER
UPDATE ON _cache_item BEGIN
UPDATE _cache_item
SET
  set_at = CURRENT_TIMESTAMP
WHERE
  item_key = NEW.item_key;
END;
SQL
            );
    }

    /**
     * Store an item under a given key
     *
     * @param mixed $value
     * @param DateTimeInterface|int|null $expires `null` or `0` if the value
     * should be cached indefinitely, otherwise a {@see DateTimeInterface} or
     * Unix timestamp representing its expiration time, or an integer
     * representing its lifetime in seconds.
     * @return $this
     */
    public function set(string $key, $value, $expires = null): self
    {
        if ($expires instanceof DateTimeInterface) {
            $expires = $expires->getTimestamp();
        } elseif (!$expires) {
            $expires = null;
        } elseif (!is_int($expires) || $expires < 0) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf(
                'Invalid $expires: %s',
                $expires
            ));
            // @codeCoverageIgnoreEnd
        } elseif ($expires < 1625061600) {
            // Assume values less than the timestamp of 1 Jul 2021 00:00:00 AEST
            // are lifetimes in seconds
            $expires += time();
        }

        $db = $this->db();
        $sql = <<<SQL
INSERT INTO
  _cache_item (item_key, item_value, expires_at)
VALUES
  (
    :item_key,
    :item_value,
    DATETIME(:expires_at, 'unixepoch')
  )
ON CONFLICT (item_key) DO
UPDATE
SET
  item_value = excluded.item_value,
  expires_at = excluded.expires_at
WHERE
  item_value IS NOT excluded.item_value
  OR expires_at IS NOT excluded.expires_at;
SQL;
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':item_key', $key, \SQLITE3_TEXT);
        $stmt->bindValue(':item_value', serialize($value), \SQLITE3_BLOB);
        $stmt->bindValue(':expires_at', $expires, \SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->close();

        return $this;
    }

    /**
     * True if an item exists and has not expired
     *
     * If `$maxAge` is `null` (the default), the item's expiration time is
     * honoured, otherwise it is ignored and the item is considered fresh if:
     *
     * - its age in seconds is less than or equal to `$maxAge`, or
     * - `$maxAge` is `0`
     *
     * @phpstan-impure
     */
    public function has(string $key, ?int $maxAge = null): bool
    {
        $where = $this->getWhere($key, $maxAge, $bind);
        $sql = <<<SQL
SELECT
  COUNT(*)
FROM
  _cache_item $where
SQL;
        $db = $this->db();
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        foreach ($bind as $param) {
            $stmt->bindValue(...$param);
        }
        /** @var SQLite3Result */
        $result = $stmt->execute();
        /** @var non-empty-array<int,int> */
        $row = $result->fetchArray(\SQLITE3_NUM);
        $stmt->close();

        return (bool) $row[0];
    }

    /**
     * Retrieve an item stored under a given key
     *
     * If `$maxAge` is `null` (the default), the item's expiration time is
     * honoured, otherwise it is ignored and the item is considered fresh if:
     *
     * - its age in seconds is less than or equal to `$maxAge`, or
     * - `$maxAge` is `0`
     *
     * @return mixed|null `null` if the item has expired or doesn't exist.
     *
     * @phpstan-impure
     */
    public function get(string $key, ?int $maxAge = null)
    {
        $where = $this->getWhere($key, $maxAge, $bind);
        $sql = <<<SQL
SELECT
  item_value
FROM
  _cache_item $where
SQL;
        $db = $this->db();
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        foreach ($bind as $param) {
            $stmt->bindValue(...$param);
        }
        /** @var SQLite3Result */
        $result = $stmt->execute();
        $row = $result->fetchArray(\SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            return null;
        }
        return unserialize($row[0]);
    }

    /**
     * Retrieve an instance of a class stored under a given key
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @return T|null `null` if the item has expired, doesn't exist or is not an
     * instance of `$class`.
     *
     * @phpstan-impure
     */
    public function getInstanceOf(string $key, string $class, ?int $maxAge = null): ?object
    {
        $store = $this->maybeAsOfNow();
        if (!$store->has($key, $maxAge)) {
            return null;
        }
        $item = $store->get($key, $maxAge);
        if (!is_object($item) || !is_a($item, $class)) {
            return null;
        }
        return $item;
    }

    /**
     * Retrieve an array stored under a given key
     *
     * @return mixed[]|null `null` if the item has expired, doesn't exist or is
     * not an array.
     *
     * @phpstan-impure
     */
    public function getArray(string $key, ?int $maxAge = null): ?array
    {
        $store = $this->maybeAsOfNow();
        if (!$store->has($key, $maxAge)) {
            return null;
        }
        $item = $store->get($key, $maxAge);
        if (!is_array($item)) {
            return null;
        }
        return $item;
    }

    /**
     * Retrieve an integer stored under a given key
     *
     * @return int|null `null` if the item has expired, doesn't exist or is not
     * an integer.
     *
     * @phpstan-impure
     */
    public function getInt(string $key, ?int $maxAge = null): ?int
    {
        $store = $this->maybeAsOfNow();
        if (!$store->has($key, $maxAge)) {
            return null;
        }
        $item = $store->get($key, $maxAge);
        if (!is_int($item)) {
            return null;
        }
        return $item;
    }

    /**
     * Retrieve a string stored under a given key
     *
     * @return string|null `null` if the item has expired, doesn't exist or is
     * not a string.
     *
     * @phpstan-impure
     */
    public function getString(string $key, ?int $maxAge = null): ?string
    {
        $store = $this->maybeAsOfNow();
        if (!$store->has($key, $maxAge)) {
            return null;
        }
        $item = $store->get($key, $maxAge);
        if (!is_string($item)) {
            return null;
        }
        return $item;
    }

    /**
     * Delete an item stored under a given key
     *
     * @return $this
     */
    public function delete(string $key): self
    {
        $db = $this->db();
        $sql = <<<SQL
DELETE FROM _cache_item
WHERE
  item_key = :item_key;
SQL;
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':item_key', $key, \SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        return $this;
    }

    /**
     * Delete all items
     *
     * @return $this
     */
    public function deleteAll(): self
    {
        $db = $this->db();
        $db->exec(
            <<<SQL
DELETE FROM _cache_item;
SQL
        );

        return $this;
    }

    /**
     * Delete expired items
     *
     * @return $this
     */
    public function flush(): self
    {
        $sql = <<<SQL
DELETE FROM _cache_item
WHERE
  expires_at <= DATETIME(:now, 'unixepoch');
SQL;
        $db = $this->db();
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':now', $this->now(), \SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->close();

        return $this;
    }

    /**
     * Retrieve an item stored under a given key, or get it from a callback and
     * store it for subsequent retrieval
     *
     * @template T
     *
     * @param callable(): T $callback
     * @param DateTimeInterface|int|null $expires `null` or `0` if the value
     * should be cached indefinitely, otherwise a {@see DateTimeInterface} or
     * Unix timestamp representing its expiration time, or an integer
     * representing its lifetime in seconds.
     * @return T
     *
     * @phpstan-impure
     */
    public function maybeGet(string $key, callable $callback, $expires = null)
    {
        $store = $this->maybeAsOfNow();
        if ($store->has($key)) {
            return $store->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $expires);

        return $value;
    }

    /**
     * Get the number of unexpired items in the store
     *
     * If `$maxAge` is `null` (the default), each item's expiration time is
     * honoured, otherwise it is ignored and items are considered fresh if:
     *
     * - their age in seconds is less than or equal to `$maxAge`, or
     * - `$maxAge` is `0`
     *
     * @phpstan-impure
     */
    public function getItemCount(?int $maxAge = null): int
    {
        $where = $this->getWhere(null, $maxAge, $bind);
        $sql = <<<SQL
SELECT
  COUNT(*)
FROM
  _cache_item $where
SQL;
        $db = $this->db();
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        foreach ($bind as $param) {
            $stmt->bindValue(...$param);
        }
        /** @var SQLite3Result */
        $result = $stmt->execute();
        /** @var non-empty-array<int,int> */
        $row = $result->fetchArray(\SQLITE3_NUM);
        $stmt->close();

        return $row[0];
    }

    /**
     * Get a list of keys under which unexpired items are stored
     *
     * If `$maxAge` is `null` (the default), each item's expiration time is
     * honoured, otherwise it is ignored and items are considered fresh if:
     *
     * - their age in seconds is less than or equal to `$maxAge`, or
     * - `$maxAge` is `0`
     *
     * @return string[]
     *
     * @phpstan-impure
     */
    public function getAllKeys(?int $maxAge = null): array
    {
        $where = $this->getWhere(null, $maxAge, $bind);
        $sql = <<<SQL
SELECT
  item_key
FROM
  _cache_item $where
SQL;
        $db = $this->db();
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        foreach ($bind as $param) {
            $stmt->bindValue(...$param);
        }
        /** @var SQLite3Result */
        $result = $stmt->execute();
        while (($row = $result->fetchArray(\SQLITE3_NUM)) !== false) {
            $keys[] = $row[0];
        }
        $result->finalize();
        $stmt->close();

        return $keys ?? [];
    }

    /**
     * Get a copy of the store where items do not expire over time
     *
     * Returns a {@see CacheStore} instance where items expire relative to the
     * time {@see CacheStore::asOfNow()} is called, allowing clients to mitigate
     * race conditions arising from items expiring between subsequent calls to
     * {@see CacheStore::has()} and {@see CacheStore::get()}, for example.
     *
     * @param int|null $now If given, items expire relative to this Unix
     * timestamp instead of the time {@see CacheStore::asOfNow()} is called.
     * @return static
     */
    public function asOfNow(?int $now = null): self
    {
        if ($this->Now !== null) {
            // @codeCoverageIgnoreStart
            throw new LogicException(
                sprintf('Calls to %s cannot be nested', __METHOD__)
            );
            // @codeCoverageIgnoreEnd
        }

        $clone = clone $this;
        $clone->Now = $now === null
            ? time()
            : $now;
        return $clone;
    }

    private function now(): int
    {
        return $this->Now === null
            ? time()
            : $this->Now;
    }

    /**
     * @return static
     */
    private function maybeAsOfNow(): self
    {
        return $this->Now === null
            ? $this->asOfNow()
            : $this;
    }

    /**
     * @param array<string,mixed> $bind
     */
    private function getWhere(?string $key, ?int $maxAge, ?array &$bind): string
    {
        $where = [];
        $bind = [];

        if ($key !== null) {
            $where[] = 'item_key = :item_key';
            $bind[] = [':item_key', $key, \SQLITE3_TEXT];
        }

        $bindNow = false;
        if ($maxAge === null) {
            $where[] = "(expires_at IS NULL OR expires_at > DATETIME(:now, 'unixepoch'))";
            $bindNow = true;
        } elseif ($maxAge) {
            $where[] = "DATETIME(set_at, :max_age) > DATETIME(:now, 'unixepoch')";
            $bind[] = [':max_age', "+$maxAge seconds", \SQLITE3_TEXT];
            $bindNow = true;
        }
        if ($bindNow) {
            $bind[] = [':now', $this->now(), \SQLITE3_INTEGER];
        }

        $where = implode(' AND ', $where);
        if ($where === '') {
            return '';
        }
        return "WHERE $where";
    }
}
