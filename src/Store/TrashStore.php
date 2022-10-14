<?php

declare(strict_types=1);

namespace Lkrms\Store;

use Lkrms\Store\Concept\SqliteStore;

/**
 * A SQLite store for deleted JSON objects
 *
 */
final class TrashStore extends SqliteStore
{
    public function __construct(string $filename = ":memory:")
    {
        $this->open($filename);
    }

    /**
     * Create or open a storage database
     *
     * @param string $filename The SQLite database to use.
     * @return $this
     */
    public function open(string $filename = ":memory:")
    {
        parent::open($filename);
        $this->db()->exec(
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
        return $this;
    }

    /**
     * Add a deleted object to the store
     *
     * @param string $type The object's canonical type.
     * @param string|null $key The object's original identifier.
     * @param array|object $object Must be JSON-serializable. No `resource`s.
     * @param string|null $deletedFrom Where was the object before it was deleted?
     * @param int|null $createdAt When was the object originally created?
     * @param int|null $modifiedAt When was the object most recently changed?
     * @return $this
     */
    public function put(string $type, ?string $key, $object,
        ?string $deletedFrom, int $createdAt = null, int $modifiedAt = null)
    {
        $this->assertIsOpen();
        $stmt = $this->db()->prepare(
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
        $stmt->close();
        return $this;
    }

    /**
     * Delete everything
     *
     * @return $this
     */
    public function empty()
    {
        $this->assertIsOpen();
        $this->db()->exec(
<<<SQL
DELETE
FROM _trash_item
SQL
        );
        return $this;
    }
}
