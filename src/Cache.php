<?php

declare(strict_types=1);

namespace Lkrms;

use RuntimeException;
use SQLite3;

/**
 * A simple SQLite object cache inspired by memcached
 *
 * @package Lkrms
 */
class Cache
{
    private static $Loaded = false;

    /**
     * @var SQLite3
     */
    private static $db;

    private static function CheckLoaded(string $method)
    {
        if ( ! self::$Loaded)
        {
            throw new RuntimeException($method . ": Load() must be called first");
        }
    }

    private static function FlushExpired()
    {
        self::$db->exec(
<<<SQL
DELETE
FROM _cache_item
WHERE expires_at <= CURRENT_TIMESTAMP
SQL
        );
    }

    /**
     * Check if a cache database is open
     *
     * @return bool
     */
    public static function IsLoaded(): bool
    {
        return self::$Loaded;
    }

    /**
     * Create or open a cache database
     *
     * Must be called before `Set()`, `Get()`, `Delete()` or `Flush()` are
     * called.
     *
     * @param string $filename The SQLite database to use.
     */
    public static function Load(string $filename = ":memory:")
    {
        self::$db = new SQLite3($filename);
        self::$db->enableExceptions();
        self::$db->exec(
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
        self::FlushExpired();
        self::$Loaded = true;
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
     * @throws RuntimeException
     */
    public static function Set(string $key, $value, int $expiry = 0)
    {
        self::CheckLoaded(__METHOD__);

        // If $expiry is non-zero and exceeds 60*60*24*30 seconds (30 days),
        // take it as a Unix timestamp, otherwise take it as seconds from now
        if ( ! $expiry)
        {
            $expiry = null;
        }
        elseif ($expiry <= 2592000)
        {
            $expiry += time();
        }

        $stmt = self::$db->prepare(
<<<SQL
INSERT INTO _cache_item(item_key, item_value, expires_at)
    VALUES (:item_key, :item_value, datetime(:expires_at, 'unixepoch'))
    ON CONFLICT(item_key) DO UPDATE SET
        item_value = excluded.item_value,
        expires_at = excluded.expires_at
    WHERE item_value IS NOT excluded.item_value
        OR expires_at IS NOT excluded.expires_at
SQL
        );
        $stmt->bindValue(":item_key", $key, SQLITE3_TEXT);
        $stmt->bindValue(":item_value", serialize($value), SQLITE3_BLOB);
        $stmt->bindValue(":expires_at", $expiry, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /**
     * Retrieve an item
     *
     * Returns the value previously stored under the `$key`, or `false` if the
     * value has expired or doesn't exist in the cache.
     *
     * @param string $key The key of the item to retrieve.
     * @return mixed The `unserialize`d value stored in the cache.
     * @throws RuntimeException
     */
    public static function Get(string $key)
    {
        self::CheckLoaded(__METHOD__);
        $stmt = self::$db->prepare(
<<<SQL
SELECT item_value
FROM _cache_item
WHERE item_key = :item_key
    AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
SQL
        );
        $stmt->bindValue(":item_key", $key, SQLITE3_TEXT);
        $result = $stmt->execute();

        if (($row = $result->fetchArray(SQLITE3_NUM)) === false)
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
     * @throws RuntimeException
     */
    public static function Delete(string $key)
    {
        self::CheckLoaded(__METHOD__);
        $stmt = self::$db->prepare(
<<<SQL
DELETE
FROM _cache_item
WHERE item_key = :item_key
SQL
        );
        $stmt->bindValue(":item_key", $key, SQLITE3_TEXT);
        $stmt->execute();
    }

    /**
     * Delete all items
     *
     * @throws RuntimeException
     */
    public static function Flush()
    {
        self::CheckLoaded(__METHOD__);
        self::$db->exec(
<<<SQL
DELETE
FROM _cache_item
SQL
        );
    }
}

