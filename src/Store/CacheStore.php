<?php declare(strict_types=1);

namespace Lkrms\Store;

use Lkrms\Store\Concept\SqliteStore;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * A SQLite-backed key-value store
 */
final class CacheStore extends SqliteStore
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
    public function set(string $key, $value, $expires = null)
    {
        if ($expires instanceof DateTimeInterface) {
            $expires = $expires->getTimestamp();
        } elseif (!$expires) {
            $expires = null;
        } elseif (!is_int($expires) || $expires < 0) {
            throw new InvalidArgumentException(sprintf(
                'Invalid $expires: %s',
                $expires
            ));
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
     */
    public function has(string $key, ?int $maxAge = null): bool
    {
        $where[] = 'item_key = :item_key';
        $bind[] = [':item_key', $key, \SQLITE3_TEXT];

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
        $sql = <<<SQL
            SELECT
              COUNT(*)
            FROM
              _cache_item
            WHERE
              $where
            SQL;
        $db = $this->db();
        $stmt = $db->prepare($sql);
        foreach ($bind as $param) {
            $stmt->bindValue(...$param);
        }
        $result = $stmt->execute();
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
     * @return mixed|false `false` if the item has expired or doesn't exist.
     */
    public function get(string $key, ?int $maxAge = null)
    {
        $where[] = 'item_key = :item_key';
        $bind[] = [':item_key', $key, \SQLITE3_TEXT];

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
        $sql = <<<SQL
            SELECT
              item_value
            FROM
              _cache_item
            WHERE
              $where
            SQL;
        $db = $this->db();
        $stmt = $db->prepare($sql);
        foreach ($bind as $param) {
            $stmt->bindValue(...$param);
        }
        $result = $stmt->execute();
        $row = $result->fetchArray(\SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            return false;
        }
        return unserialize($row[0]);
    }

    /**
     * Delete an item stored under a given key
     *
     * @return $this
     */
    public function delete(string $key)
    {
        $db = $this->db();
        $sql = <<<SQL
            DELETE FROM
              _cache_item
            WHERE
              item_key = :item_key;
            SQL;
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
    public function deleteAll()
    {
        $db = $this->db();
        $db->exec(
            <<<SQL
            DELETE FROM
              _cache_item;
            SQL
        );

        return $this;
    }

    /**
     * Delete expired items
     *
     * @return $this
     */
    public function flush()
    {
        $db = $this->db();
        $db->exec(
            <<<SQL
            DELETE FROM
              _cache_item
            WHERE
              expires_at <= CURRENT_TIMESTAMP;
            SQL
        );

        return $this;
    }

    /**
     * Retrieve an item stored under a given key, or get it from a callback and
     * store it for subsequent retrieval
     *
     * @param callable(): mixed $callback
     * @param DateTimeInterface|int|null $expires `null` or `0` if the value
     * should be cached indefinitely, otherwise a {@see DateTimeInterface} or
     * Unix timestamp representing its expiration time, or an integer
     * representing its lifetime in seconds.
     * @return mixed
     */
    public function maybeGet(string $key, callable $callback, $expires = null)
    {
        $store = $this->asOfNow();
        if ($store->has($key)) {
            return $store->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $expires);

        return $value;
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
     * @return $this
     */
    public function asOfNow(?int $now = null)
    {
        if ($now === null && $this->Now !== null) {
            return $this;
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
}
