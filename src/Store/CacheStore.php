<?php

declare(strict_types=1);

namespace Lkrms\Store;

use SQLite3;
use Throwable;

/**
 * A SQLite object cache inspired by memcached
 *
 */
final class CacheStore extends SqliteStore
{
    /**
     * Create or open a cache database
     *
     * @param string $filename The SQLite database to use.
     * @param bool $autoFlush Automatically flush expired values?
     */
    public function __construct(string $filename = ":memory:", bool $autoFlush = true)
    {
        $this->open($filename);
        $this->db()->exec(
<<<SQL
CREATE TABLE IF NOT EXISTS _cache_item (
    item_key TEXT NOT NULL PRIMARY KEY,
    item_value BLOB,
    expires_at DATETIME,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    set_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER IF NOT EXISTS _cache_item_update
AFTER UPDATE ON _cache_item
BEGIN
   UPDATE _cache_item SET set_at = CURRENT_TIMESTAMP WHERE rowid = NEW.rowid;
END;
SQL
        );

        if ($autoFlush)
        {
            $this->flushExpired();
        }
    }

    /**
     * Store an item
     *
     * Stores the `$value` under the specified `$key` until the cache is flushed
     * or `$expiry` seconds have passed.
     *
     * @param string $key The key under which to store the value.
     * @param mixed $value The value to `serialize` and store.
     * @param int $expiry The time in seconds before `$value` expires (maximum
     * 30 days), or the expiry time's Unix timestamp. `0` = no expiry.
     */
    public function set(string $key, $value, int $expiry = 0): void
    {
        $this->assertIsOpen();

        try
        {
            $this->beginTransaction();

            if ($value === false)
            {
                $this->delete($key);

                return;
            }

            // If $expiry is non-zero and exceeds 60*60*24*30 seconds (30 days),
            // take it as a Unix timestamp, otherwise take it as seconds from
            // now
            if (!$expiry)
            {
                $expiry = null;
            }
            elseif ($expiry <= 2592000)
            {
                $expiry += time();
            }

            $sql = [];

            if (version_compare((SQLite3::version())["versionString"], "3.24") >= 0)
            {
                $sql[] = <<<SQL
INSERT INTO _cache_item(item_key, item_value, expires_at)
VALUES (
    :item_key,
    :item_value,
    datetime(:expires_at, 'unixepoch')
  )
ON CONFLICT(item_key) DO
  UPDATE
  SET item_value = excluded.item_value,
    expires_at = excluded.expires_at
  WHERE item_value IS NOT excluded.item_value
    OR expires_at IS NOT excluded.expires_at;
SQL;
            }
            else
            {
                // SQLite 3.24 was only released in June 2018 (after Ubuntu
                // 18.04), so it isn't ubiquitous enough to get away with no
                // UPSERT shim
                $sql[] = <<<SQL
UPDATE _cache_item
SET item_value = :item_value,
  expires_at = datetime(:expires_at, 'unixepoch')
WHERE item_key = :item_key
SQL;
                $sql[] = <<<SQL
INSERT OR IGNORE INTO _cache_item(item_key, item_value, expires_at)
VALUES (
    :item_key,
    :item_value,
    datetime(:expires_at, 'unixepoch')
  )
SQL;
            }

            foreach ($sql as $_sql)
            {
                $stmt = $this->db()->prepare($_sql);
                $stmt->bindValue(":item_key", $key, SQLITE3_TEXT);
                $stmt->bindValue(":item_value", serialize($value), SQLITE3_BLOB);
                $stmt->bindValue(":expires_at", $expiry, SQLITE3_INTEGER);
                $stmt->execute();
                $stmt->close();
            }

            $this->commitTransaction();
        }
        catch (Throwable $ex)
        {
            $this->rollbackTransaction();
            throw $ex;
        }
    }

    /**
     * Retrieve an item
     *
     * Returns the value previously stored under the `$key`, or `false` if the
     * value has expired or doesn't exist in the cache.
     *
     * @param string $key The key of the item to retrieve.
     * @param int $maxAge The time in seconds before stored values should be
     * considered expired (maximum 30 days). Overrides stored expiry times for
     * this request only. `0` = no expiry.
     * @return mixed The `unserialize`d value stored in the cache.
     */
    public function get(string $key, int $maxAge = null)
    {
        $this->assertIsOpen();

        $sql = [
            "item_key = :item_key"
        ];
        $bind = [
            [":item_key", $key, SQLITE3_TEXT]
        ];

        if (is_null($maxAge) || $maxAge > 2592000)
        {
            $sql[] = "(expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)";
        }
        elseif ($maxAge)
        {
            $sql[]  = "datetime(set_at, :max_age) > CURRENT_TIMESTAMP";
            $bind[] = [":max_age", "+$maxAge seconds", SQLITE3_TEXT];
        }

        $stmt = $this->db()->prepare("SELECT item_value FROM _cache_item WHERE " . implode(" AND ", $sql));

        foreach ($bind as $param)
        {
            $stmt->bindValue(...$param);
        }

        $result = $stmt->execute();
        $row    = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        if ($row === false)
        {
            return false;
        }
        else
        {
            return unserialize($row[0]);
        }
    }

    /**
     * Delete an item
     *
     * Deletes the value stored under the `$key` from the cache.
     *
     * @param string $key The key of the item to delete.
     */
    public function delete(string $key): void
    {
        $this->assertIsOpen();

        $stmt = $this->db()->prepare(
<<<SQL
DELETE
FROM _cache_item
WHERE item_key = :item_key
SQL
        );
        $stmt->bindValue(":item_key", $key, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Delete all items
     *
     */
    public function flush(): void
    {
        $this->assertIsOpen();

        $this->db()->exec(
<<<SQL
DELETE
FROM _cache_item
SQL
        );
    }

    /**
     * Delete expired items
     *
     */
    public function flushExpired(): void
    {
        $this->assertIsOpen();

        $this->db()->exec(
<<<SQL
DELETE
FROM _cache_item
WHERE expires_at <= CURRENT_TIMESTAMP
SQL
        );
    }

    /**
     * Retrieve an item, or get it from a callback and store it for next time
     *
     * @param string $key
     * @param callable $callback
     * @param int $expiry
     * @return mixed
     */
    public function maybeGet(string $key, callable $callback, int $expiry = 0)
    {
        if (($value = $this->get($key, $expiry)) === false)
        {
            $value = $callback();
            $this->set($key, $value, $expiry);
        }

        return $value;
    }
}
