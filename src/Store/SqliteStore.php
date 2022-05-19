<?php

declare(strict_types=1);

namespace Lkrms\Store;

use Exception;
use Lkrms\Console\Console;
use Lkrms\Util\File;
use RuntimeException;
use SQLite3;

/**
 * Base class for SQLite-backed stores
 *
 * @package Lkrms\Service
 */
abstract class SqliteStore
{
    /**
     * @var SQLite3|null
     */
    private $Db;

    /**
     * @var string|null
     */
    private $Filename;

    /**
     * @var bool
     */
    private $IsTransactionOpen;

    /**
     * Create or open a database
     *
     * @param string $filename
     */
    public function open(string $filename = ":memory:")
    {
        $this->close();

        if ($filename != ":memory:")
        {
            File::maybeCreate($filename, 0600, 0700);
        }

        $db = new SQLite3($filename);
        $db->enableExceptions();
        $db->busyTimeout(60000);
        $db->exec('PRAGMA journal_mode=WAL');
        $this->Db       = $db;
        $this->Filename = $filename;
    }

    /**
     * If a database is open, close it
     */
    public function close()
    {
        if (!$this->isOpen())
        {
            return;
        }

        $this->db()->close();
        $this->Db = $this->Filename = null;
    }

    /**
     * Check if a database is open
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return !is_null($this->Db);
    }

    /**
     * Get the filename of the database
     *
     * @return null|string
     */
    public function getFilename(): ?string
    {
        return $this->Filename;
    }

    /**
     * Throw an exception if a database is not open
     *
     * @throws RuntimeException
     */
    protected function assertIsOpen()
    {
        if (!$this->isOpen())
        {
            throw new RuntimeException("open() must be called first");
        }
    }

    /**
     * Get the open SQLite3 instance
     *
     * Call {@see Sqlite::assertIsOpen()} first to ensure the return value is
     * not `null`.
     *
     * @return null|SQLite3 .
     */
    protected function db(): ?SQLite3
    {
        return $this->Db;
    }

    protected function isTransactionOpen(): bool
    {
        return $this->IsTransactionOpen ?: false;
    }

    protected function beginTransaction()
    {
        if ($this->isTransactionOpen())
        {
            Console::debug("Transaction already open");

            return;
        }

        $this->db()->exec("BEGIN");
        $this->IsTransactionOpen = true;
    }

    protected function commitTransaction()
    {
        if (!$this->isTransactionOpen())
        {
            Console::debug("No transaction open");

            return;
        }

        $this->db()->exec("COMMIT");
        $this->IsTransactionOpen = false;
    }

    protected function rollbackTransaction()
    {
        // Silently ignore transactionless rollback requests, e.g. when an
        // exception is caught in a Schrödinger's transaction scenario
        if (!$this->isTransactionOpen())
        {
            return;
        }

        $this->db()->exec("ROLLBACK");
        $this->IsTransactionOpen = false;
    }

    protected function invokeInTransaction(callable $callback)
    {
        if ($this->isTransactionOpen())
        {
            throw new RuntimeException("Transaction already open");
        }

        $this->beginTransaction();

        try
        {
            $result = $callback();
            $this->commitTransaction();
        }
        catch (Exception $ex)
        {
            $this->rollbackTransaction();
            throw $ex;
        }

        return $result;
    }
}
