<?php

declare(strict_types=1);

namespace Lkrms;

use RuntimeException;
use SQLite3;

/**
 * A SQLite store for deleted JSON objects
 *
 * @package Lkrms\Service
 */
class Trash
{
    private static $Loaded = false;

    /**
     * @var SQLite3
     */
    private static $db;

    private static function CheckLoaded(string $method)
    {
        if (! static::$Loaded)
        {
            throw new RuntimeException($method . ": Load() must be called first");
        }
    }

    /**
     * Check if database is open
     *
     * @return bool
     */
    public static function IsLoaded(): bool
    {
        return static::$Loaded;
    }

    /**
     * Create or open a storage database
     *
     * Must be called before {@see Trash::Put()} or {@see Trash::Empty()} are
     * called.
     *
     * @param string $filename The SQLite database to use.
     */
    public static function Load(string $filename)
    {
        if ($filename != ":memory:")
        {
            File::MaybeCreate($filename, 0600, 0700);
        }

        self::$db = new SQLite3($filename);
        self::$db->enableExceptions();
        self::$db->exec(
<<<SQL
CREATE TABLE IF NOT EXISTS _trash_item (
  item_type TEXT NOT NULL,
  item_key TEXT,
  item_json TEXT NOT NULL,
  deleted_from TEXT,
  deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME,
  modified_at DATETIME
)
SQL
        );
        self::$Loaded = true;
    }

    /**
     * Add a deleted object to the store
     *
     * @param string $type The object's canonical type.
     * @param null|string $key The object's original identifier.
     * @param array|object $object Must be JSON-serializable. No `resource`s.
     * @param null|string $deletedFrom Where was the object before it was
     * deleted?
     * @param int|null $createdAt When was the object originally created?
     * @param int|null $modifiedAt When was the object most recently changed?
     * @throws RuntimeException
     */
    public static function Put(string $type, ?string $key, $object,
        ?string $deletedFrom, int $createdAt = null, int $modifiedAt = null)
    {
        self::CheckLoaded(__METHOD__);
        $stmt = self::$db->prepare(
<<<SQL
INSERT INTO _trash_item(
    item_type,
    item_key,
    item_json,
    deleted_from,
    created_at,
    modified_at
  )
VALUES (
    :item_type,
    :item_key,
    :item_json,
    :deleted_from,
    datetime(:created_at, 'unixepoch'),
    datetime(:modified_at, 'unixepoch')
  )
SQL
        );
        $stmt->bindValue(":item_type", $type, SQLITE3_TEXT);
        $stmt->bindValue(":item_key", $key, SQLITE3_TEXT);
        $stmt->bindValue(":item_json", json_encode($object), SQLITE3_TEXT);
        $stmt->bindValue(":deleted_from", $deletedFrom, SQLITE3_TEXT);
        $stmt->bindValue(":created_at", $createdAt, SQLITE3_INTEGER);
        $stmt->bindValue(":modified_at", $modifiedAt, SQLITE3_INTEGER);
        $stmt->execute();
    }

    /**
     * Delete everything
     *
     * @throws RuntimeException
     */
    public static function Empty()
    {
        self::CheckLoaded(__METHOD__);
        self::$db->exec(
<<<SQL
DELETE
FROM _trash_item
SQL
        );
    }
}

