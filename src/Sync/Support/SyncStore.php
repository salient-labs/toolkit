<?php

declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Store\Concept\SqliteStore;

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
CREATE TABLE IF NOT EXISTS _sync_run (
  run_id INTEGER NOT NULL PRIMARY KEY,
  run_uuid BLOB NOT NULL UNIQUE,
  run_command TEXT NOT NULL,
  run_arguments_json TEXT NOT NULL,
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME,
  exit_status INTEGER
);

CREATE TABLE IF NOT EXISTS _sync_provider (
  provider_id INTEGER NOT NULL PRIMARY KEY,
  provider_hash BLOB NOT NULL UNIQUE,
  provider_class TEXT NOT NULL,
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
