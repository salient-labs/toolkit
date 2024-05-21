<?php declare(strict_types=1);

namespace Salient\Cache;

use Salient\Contract\Cache\CacheStoreInterface;
use Salient\Core\Exception\LogicException;
use Salient\Core\AbstractStore;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use SQLite3Result;
use SQLite3Stmt;

/**
 * A SQLite-backed key-value store
 *
 * Expired items are not implicitly flushed. {@see CacheStore::flush()} must be
 * called explicitly, e.g. on a schedule or once per run.
 */
final class CacheStore extends AbstractStore implements CacheStoreInterface
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
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null): bool
    {
        if ($ttl === null) {
            $expires = null;
        } elseif ($ttl instanceof DateInterval) {
            $expires = (new DateTimeImmutable())->add($ttl)->getTimestamp();
        } elseif ($ttl instanceof DateTimeInterface) {
            $expires = $ttl->getTimestamp();
        } elseif ($ttl > 0) {
            $expires = time() + $ttl;
        } else {
            return $this->delete($key);
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

        return true;
    }

    /**
     * @phpstan-impure
     */
    public function has($key, ?int $maxAge = null): bool
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
     * @phpstan-impure
     */
    public function get($key, $default = null, ?int $maxAge = null)
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

        return $row === false
            ? $default
            : unserialize($row[0]);
    }

    /**
     * @phpstan-impure
     */
    public function getInstanceOf($key, string $class, ?object $default = null, ?int $maxAge = null): ?object
    {
        $store = $this->maybeAsOfNow();
        if (!$store->has($key, $maxAge)) {
            return $default;
        }
        $item = $store->get($key, $default, $maxAge);
        if (!is_object($item) || !is_a($item, $class)) {
            return $default;
        }
        return $item;
    }

    /**
     * @phpstan-impure
     */
    public function getArray($key, ?array $default = null, ?int $maxAge = null): ?array
    {
        $store = $this->maybeAsOfNow();
        if (!$store->has($key, $maxAge)) {
            return $default;
        }
        $item = $store->get($key, $default, $maxAge);
        if (!is_array($item)) {
            return $default;
        }
        return $item;
    }

    /**
     * @phpstan-impure
     */
    public function getInt($key, ?int $default = null, ?int $maxAge = null): ?int
    {
        $store = $this->maybeAsOfNow();
        if (!$store->has($key, $maxAge)) {
            return $default;
        }
        $item = $store->get($key, $default, $maxAge);
        if (!is_int($item)) {
            return $default;
        }
        return $item;
    }

    /**
     * @phpstan-impure
     */
    public function getString($key, ?string $default = null, ?int $maxAge = null): ?string
    {
        $store = $this->maybeAsOfNow();
        if (!$store->has($key, $maxAge)) {
            return $default;
        }
        $item = $store->get($key, $default, $maxAge);
        if (!is_string($item)) {
            return $default;
        }
        return $item;
    }

    /**
     * @inheritDoc
     */
    public function delete($key): bool
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

        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $db = $this->db();
        $db->exec(
            <<<SQL
DELETE FROM _cache_item;
SQL
        );

        return true;
    }

    /**
     * Delete expired items
     *
     * @return true
     */
    public function clearExpired(): bool
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

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null, ?int $maxAge = null)
    {
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default, $maxAge);
        }
        return $values ?? [];
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
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
     * @inheritDoc
     */
    public function asOfNow(?int $now = null): CacheStoreInterface
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
        return $this->Now ?? time();
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
