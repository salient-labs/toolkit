<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Sync\Concept\SyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use ReflectionClass;
use RuntimeException;
use UnexpectedValueException;

/**
 * Tracks the state of entities synced to and from third-party backends in a
 * local SQLite database
 *
 */
final class SyncStore extends SqliteStore
{
    /**
     * @var int
     */
    private $RunId;

    /**
     * @var string
     */
    private $RunUuid;

    /**
     * Provider ID => provider
     *
     * @var array<int,ISyncProvider>
     */
    private $Providers = [];

    /**
     * Provider hash => provider
     *
     * @var array<string,ISyncProvider>
     */
    private $ProvidersByHash = [];

    /**
     * Entity class => entity type ID
     *
     * @var array<string,int>
     */
    private $EntityTypes = [];

    /**
     * Initiate a "run" of sync operations
     *
     * @param string $command The canonical name of the command performing sync
     * operations (e.g. a qualified class and/or method name).
     * @param array $arguments Arguments passed to the command.
     */
    public function __construct(string $filename = ":memory:", string $command = "", array $arguments = [])
    {
        $this->requireUpsert();

        $this->open($filename);
        $this->startRun($command, $arguments);
    }

    /**
     * Create or open a sync entity database
     *
     * @return $this
     */
    private function open(string $filename)
    {
        $this->openDb($filename);

        $db = $this->db();
        $db->exec(
<<<SQL
CREATE TABLE IF NOT EXISTS
  _sync_run (
    run_id INTEGER NOT NULL PRIMARY KEY,
    run_uuid BLOB NOT NULL UNIQUE,
    run_command TEXT NOT NULL,
    run_arguments_json TEXT NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME,
    exit_status INTEGER
  );

CREATE TABLE IF NOT EXISTS
  _sync_provider (
    provider_id INTEGER NOT NULL PRIMARY KEY,
    provider_hash BLOB NOT NULL UNIQUE,
    provider_class TEXT NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
  );

CREATE TABLE IF NOT EXISTS
  _sync_entity_type (
    entity_type_id INTEGER NOT NULL PRIMARY KEY,
    entity_type_class TEXT NOT NULL UNIQUE,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
  );

SQL
        );

        return $this;
    }

    public function close(?int $exitStatus = 0)
    {
        if (!$this->isOpen())
        {
            return $this;
        }

        $db  = $this->db();
        $sql = <<<SQL
UPDATE _sync_run
SET finished_at = CURRENT_TIMESTAMP,
  exit_status = :exit_status
WHERE run_uuid = :run_uuid;
SQL;

        $stmt = $db->prepare($sql);
        $stmt->bindValue(":exit_status", $exitStatus, SQLITE3_INTEGER);
        $stmt->bindValue(":run_uuid", $this->RunUuid, SQLITE3_BLOB);
        $stmt->execute();
        $stmt->close();

        return parent::close();
    }

    /**
     * Get the run ID of the current run
     *
     */
    public function getRunId(): int
    {
        return $this->RunId;
    }

    /**
     * Get the UUID of the current run
     *
     * @param bool $binary If `true`, return 16 bytes of raw binary data,
     * otherwise return a 36-byte hexadecimal representation.
     */
    public function getRunUuid(bool $binary = false): string
    {
        return $binary ? $this->RunUuid : Convert::uuidToHex($this->RunUuid);
    }

    /**
     * Register a sync provider and set its provider ID
     *
     * @return $this
     */
    public function provider(ISyncProvider $provider)
    {
        $class = get_class($provider);
        $hash  = Compute::binaryHash($class, ...$provider->getBackendIdentifier());

        if (!is_null($this->ProvidersByHash[$hash] ?? null))
        {
            throw new RuntimeException("Provider already registered: $class");
        }

        // Update `last_seen` if the provider is already in the database
        $db  = $this->db();
        $sql = <<<SQL
INSERT INTO
  _sync_provider (provider_hash, provider_class)
VALUES
  (:provider_hash, :provider_class) ON CONFLICT (provider_hash) DO
UPDATE
SET
  last_seen = CURRENT_TIMESTAMP;
SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":provider_hash", $hash, SQLITE3_BLOB);
        $stmt->bindValue(":provider_class", $class, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $sql = <<<SQL
SELECT
  provider_id
FROM
  _sync_provider
WHERE
  provider_hash = :provider_hash;
SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":provider_hash", $hash, SQLITE3_BLOB);
        $result = $stmt->execute();
        $row    = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        if ($row === false)
        {
            throw new RuntimeException("Error retrieving provider ID");
        }

        $provider->setProviderId($row[0], $hash);
        $this->Providers[$row[0]] = $this->ProvidersByHash[$hash] = $provider;

        return $this;
    }

    /**
     * Register a sync entity type and set its ID unless already registered
     *
     * @return $this
     */
    public function entityType(string $entity)
    {
        if (!is_null($this->EntityTypes[$entity] ?? null))
        {
            return $this;
        }

        $class = new ReflectionClass($entity);
        if (!$class->isSubclassOf(SyncEntity::class))
        {
            throw new UnexpectedValueException("Not a subclass of SyncEntity: $entity");
        }

        // Update `last_seen` if the entity type is already in the database
        $db  = $this->db();
        $sql = <<<SQL
INSERT INTO
  _sync_entity_type (entity_type_class)
VALUES
  (:entity_type_class) ON CONFLICT (entity_type_class) DO
UPDATE
SET
  last_seen = CURRENT_TIMESTAMP;
SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":entity_type_class", $class->name, SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $sql = <<<SQL
SELECT
  entity_type_id
FROM
  _sync_entity_type
WHERE
  entity_type_class = :entity_type_class;
SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue(":entity_type_class", $class->name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row    = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        if ($row === false)
        {
            throw new RuntimeException("Error retrieving entity type ID");
        }

        $class->getMethod("setEntityTypeId")->invoke(null, $row[0]);
        $this->EntityTypes[$entity] = $row[0];

        return $this;
    }

    private function startRun(string $command, array $arguments)
    {
        $db  = $this->db();
        $sql = <<<SQL
INSERT INTO _sync_run (run_uuid, run_command, run_arguments_json)
VALUES (
    :run_uuid,
    :run_command,
    :run_arguments_json
  );
SQL;

        $stmt = $db->prepare($sql);
        $stmt->bindValue(":run_uuid", $uuid = Compute::uuid(true), SQLITE3_BLOB);
        $stmt->bindValue(":run_command", $command, SQLITE3_TEXT);
        $stmt->bindValue(":run_arguments_json", json_encode($arguments), SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $id = $db->lastInsertRowID();
        $this->RunId   = $id;
        $this->RunUuid = $uuid;
    }

}
