<?php declare(strict_types=1);

namespace Lkrms\Store;

use Lkrms\Store\Concept\SqliteStore;
use UnexpectedValueException;

/**
 * A SQLite store for deleted objects
 *
 */
final class TrashStore extends SqliteStore
{
    public function __construct(string $filename = ':memory:')
    {
        $this->open($filename);
    }

    /**
     * Create or open a storage database
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
  _trash_item (
    item_type TEXT NOT NULL,
    item_key TEXT,
    item_json TEXT NOT NULL,
    deleted_from TEXT,
    deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME,
    modified_at DATETIME
  );
SQL
        );

        return $this;
    }

    /**
     * Add a deleted object to the store
     *
     * @param string|null $key The object's identifier, if known.
     * @param array|object $object
     * @param string|null $type The object's canonical type, or `null` to use
     * its FQCN.
     * @param string|null $deletedFrom The canonical location of the object
     * before it was deleted. Need not be unique.
     * @param int|null $createdAt A Unix timestamp representing the object's
     * original creation time.
     * @param int|null $modifiedAt A Unix timestamp representing the object's
     * last modification time (before it was deleted, preferably).
     * @return $this
     */
    public function put(
        ?string $key,
        $object,
        ?string $type = null,
        ?string $deletedFrom = null,
        ?int $createdAt = null,
        ?int $modifiedAt = null
    ) {
        if (!$type && !is_object($object)) {
            throw new UnexpectedValueException('When argument #3 ($type) is null, argument #2 ($object) must be an object');
        }

        $db = $this->db();
        $sql = <<<SQL
INSERT INTO
  _trash_item (
    item_type,
    item_key,
    item_json,
    deleted_from,
    created_at,
    modified_at
  )
VALUES
  (
    :item_type,
    :item_key,
    :item_json,
    :deleted_from,
    DATETIME(:created_at, 'unixepoch'),
    DATETIME(:modified_at, 'unixepoch')
  )
SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':item_type', $type ?: get_class($object), SQLITE3_TEXT);
        $stmt->bindValue(':item_key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':item_json', json_encode($object), SQLITE3_TEXT);
        $stmt->bindValue(':deleted_from', $deletedFrom, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', $createdAt, SQLITE3_INTEGER);
        $stmt->bindValue(':modified_at', $modifiedAt, SQLITE3_INTEGER);
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
        $db = $this->db();
        $db->exec(
            <<<SQL
DELETE FROM
  _trash_item
SQL
        );

        return $this;
    }
}
