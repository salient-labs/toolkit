<?php declare(strict_types=1);

namespace Lkrms\Store;

use Lkrms\Facade\Console;
use Lkrms\Store\Concept\SqliteStore;

/**
 * A SQLite object cache inspired by memcached
 *
 */
final class CacheStore extends SqliteStore
{
    /**
     * @var bool|null
     */
    private $FlushedExpired;

    public function __construct(string $filename = ':memory:')
    {
        $this
            ->requireUpsert()
            ->open($filename);
    }

    /**
     * Create or open a cache database
     *
     * @return $this
     */
    public function open(string $filename = ':memory:')
    {
        $this->openDb($filename);

        $db = $this->db();
        $db->exec(
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
            UPDATE
              ON _cache_item BEGIN
            UPDATE
              _cache_item
            SET
              set_at = CURRENT_TIMESTAMP
            WHERE
              item_key = NEW.item_key;
            END;
            SQL
        );

        return $this;
    }

    private function maybeFlush(): void
    {
        if (!$this->FlushedExpired) {
            $this->flushExpired();
            $this->FlushedExpired = true;
        }
    }

    /**
     * Store an item
     *
     * Stores `$value` under `$key` until the cache is flushed or `$expiry`
     * seconds have passed.
     *
     * @param int $expiry The time in seconds before `$value` expires (maximum
     * 30 days), or the expiry time's Unix timestamp. `0` = no expiry.
     * @return $this
     */
    public function set(string $key, $value, int $expiry = 0)
    {
        if ($value === false) {
            $this->delete($key);

            return $this;
        }

        $this->maybeFlush();

        // If $expiry is non-zero and exceeds 60*60*24*30 seconds (30 days),
        // take it as a Unix timestamp, otherwise take it as seconds from now
        if (!$expiry) {
            $expiry = null;
        } elseif ($expiry <= 2592000) {
            $expiry += time();
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
              ) ON CONFLICT (item_key) DO
            UPDATE
            SET
              item_value = excluded.item_value,
              expires_at = excluded.expires_at
            WHERE
              item_value IS NOT excluded.item_value
              OR expires_at IS NOT excluded.expires_at;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':item_key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':item_value', serialize($value), SQLITE3_BLOB);
        $stmt->bindValue(':expires_at', $expiry, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->close();

        Console::debug('_cache_item changes:', (string) $db->changes());

        return $this;
    }

    /**
     * True if an item exists and has not expired
     *
     * @param int|null $maxAge The time in seconds before stored values should
     * be considered expired (maximum 30 days). Overrides stored expiry times
     * for this request only. `0` = no expiry.
     */
    public function has(string $key, ?int $maxAge = null): bool
    {
        $where[] = 'item_key = :item_key';
        $bind[] = [':item_key', $key, SQLITE3_TEXT];

        if (is_null($maxAge) || $maxAge > 2592000) {
            $where[] = '(expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)';
        } elseif ($maxAge) {
            $where[] = 'DATETIME(set_at, :max_age) > CURRENT_TIMESTAMP';
            $bind[] = [':max_age', "+$maxAge seconds", SQLITE3_TEXT];
        }

        $db = $this->db();
        $sql = <<<SQL
            SELECT
              COUNT(*)
            FROM
              _cache_item
            SQL;
        $stmt = $db->prepare("$sql WHERE " . implode(' AND ', $where));
        foreach ($bind as $param) {
            $stmt->bindValue(...$param);
        }
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        return (bool) $row[0];
    }

    /**
     * Retrieve an item
     *
     * Returns the value previously stored under `$key`, or `false` if it has
     * expired or doesn't exist in the cache.
     *
     * @param int|null $maxAge The time in seconds before stored values should
     * be considered expired (maximum 30 days). Overrides stored expiry times
     * for this request only. `0` = no expiry.
     */
    public function get(string $key, ?int $maxAge = null)
    {
        $where[] = 'item_key = :item_key';
        $bind[] = [':item_key', $key, SQLITE3_TEXT];

        if (is_null($maxAge) || $maxAge > 2592000) {
            $where[] = '(expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)';
        } elseif ($maxAge) {
            $where[] = 'DATETIME(set_at, :max_age) > CURRENT_TIMESTAMP';
            $bind[] = [':max_age', "+$maxAge seconds", SQLITE3_TEXT];
        }

        $db = $this->db();
        $sql = <<<SQL
            SELECT
              item_value
            FROM
              _cache_item
            SQL;
        $stmt = $db->prepare("$sql WHERE " . implode(' AND ', $where));
        foreach ($bind as $param) {
            $stmt->bindValue(...$param);
        }
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            return false;
        } else {
            return unserialize($row[0]);
        }
    }

    /**
     * Delete an item
     *
     * Deletes the value stored under `$key` from the cache.
     *
     * @return $this
     */
    public function delete(string $key)
    {
        $this->maybeFlush();

        $db = $this->db();
        $sql = <<<SQL
            DELETE FROM
              _cache_item
            WHERE
              item_key = :item_key;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':item_key', $key, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        Console::debug('_cache_item changes:', (string) $db->changes());

        return $this;
    }

    /**
     * Delete all items
     *
     * @return $this
     */
    public function flush()
    {
        $db = $this->db();
        $db->exec(
            <<<SQL
            DELETE FROM
              _cache_item;
            SQL
        );

        Console::debug('_cache_item changes:', (string) $db->changes());

        return $this;
    }

    /**
     * Delete expired items
     *
     * @return $this
     */
    public function flushExpired()
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

        Console::debug('_cache_item changes:', (string) $db->changes());

        return $this;
    }

    /**
     * Retrieve an item, or get it from a callback and store it for next time
     *
     */
    public function maybeGet(string $key, callable $callback, int $expiry = 0)
    {
        if (($value = $this->get($key, $expiry)) === false) {
            $value = $callback();
            $this->set($key, $value, $expiry);
        }

        return $value;
    }
}
