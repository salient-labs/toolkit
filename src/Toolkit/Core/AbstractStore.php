<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Concern\UnloadsFacades;
use Salient\Core\Contract\FacadeAwareInterface;
use Salient\Core\Contract\FacadeInterface;
use Salient\Core\Contract\Unloadable;
use Salient\Core\Exception\InvalidRuntimeConfigurationException;
use Salient\Core\Utility\File;
use LogicException;
use SQLite3;
use Throwable;

/**
 * Base class for SQLite-backed stores
 *
 * @api
 *
 * @implements FacadeAwareInterface<FacadeInterface<static>>
 */
abstract class AbstractStore implements FacadeAwareInterface, Unloadable
{
    /** @use UnloadsFacades<FacadeInterface<static>> */
    use UnloadsFacades;

    private ?SQLite3 $Db = null;

    private string $Filename;

    private bool $IsTransactionOpen = false;

    private bool $IsCheckRunning = false;

    /**
     * Create or open a database
     *
     * @return $this
     * @throws LogicException if a database is already open.
     */
    final protected function openDb(string $filename, ?string $query = null)
    {
        if ($this->Db) {
            throw new LogicException('Database already open');
        }

        if ($filename !== ':memory:') {
            File::create($filename, 0600, 0700);
        }

        $db = new SQLite3($filename);

        if (\PHP_VERSION_ID < 80300) {
            $db->enableExceptions();
        }

        $db->busyTimeout(60000);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA foreign_keys=ON');

        if ($query) {
            $db->exec($query);
        }

        $this->Db = $db;
        $this->Filename = $filename;

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function unload(): void
    {
        $this->close();
    }

    /**
     * Close the database
     *
     * @return $this
     */
    public function close()
    {
        return $this->closeDb();
    }

    /**
     * If a database is open, close it
     *
     * @return $this
     */
    final protected function closeDb()
    {
        if (!$this->Db) {
            $this->unloadFacades();
            return $this;
        }

        $this->Db->close();
        $this->Db = null;
        unset($this->Filename);

        $this->unloadFacades();

        return $this;
    }

    /**
     * Override to perform an action whenever the open SQLite3 instance is
     * accessed
     *
     * Called once per call to {@see AbstractStore::db()}.
     *
     * @return $this
     */
    protected function check()
    {
        return $this;
    }

    /**
     * Check if a database is open
     */
    final public function isOpen(): bool
    {
        return (bool) $this->Db;
    }

    /**
     * Get the filename of the database
     *
     * @throws LogicException if the database is not open.
     */
    final public function getFilename(): string
    {
        $this->assertIsOpen();

        return $this->Filename;
    }

    /**
     * True if check() is running via safeCheck()
     */
    final protected function isCheckRunning(): bool
    {
        return $this->IsCheckRunning;
    }

    /**
     * Call check() without recursion
     *
     * {@see AbstractStore::db()} calls {@see AbstractStore::check()} via
     * {@see AbstractStore::safeCheck()} to prevent recursion when
     * {@see AbstractStore::check()} calls {@see AbstractStore::db()}.
     *
     * If {@see AbstractStore::check()} may be called directly, it should call
     * itself via {@see AbstractStore::safeCheck()}, for example:
     *
     * ```php
     * protected function check()
     * {
     *     if (!$this->isCheckRunning()) {
     *         return $this->safeCheck();
     *     }
     *
     *     // ...
     * }
     * ```
     *
     * @return $this
     */
    final protected function safeCheck()
    {
        $this->IsCheckRunning = true;
        try {
            return $this->check();
        } finally {
            $this->IsCheckRunning = false;
        }
    }

    /**
     * Get the open SQLite3 instance
     *
     * @throws LogicException if the database is not open.
     */
    final protected function db(): SQLite3
    {
        $this->assertIsOpen();

        if ($this->IsCheckRunning) {
            return $this->Db;
        }

        return $this->safeCheck()->Db;
    }

    final protected function isTransactionOpen(): bool
    {
        return $this->Db && $this->IsTransactionOpen;
    }

    /**
     * BEGIN a transaction
     *
     * @return $this
     * @throws LogicException if a transaction is already open.
     */
    final protected function beginTransaction()
    {
        if ($this->Db && $this->IsTransactionOpen) {
            throw new LogicException('Transaction already open');
        }

        $this->db()->exec('BEGIN');
        $this->IsTransactionOpen = true;

        return $this;
    }

    /**
     * COMMIT a transaction
     *
     * @return $this
     * @throws LogicException if no transaction is open.
     */
    final protected function commitTransaction()
    {
        if ($this->Db && !$this->IsTransactionOpen) {
            throw new LogicException('No transaction open');
        }

        $this->db()->exec('COMMIT');
        $this->IsTransactionOpen = false;

        return $this;
    }

    /**
     * ROLLBACK a transaction
     *
     * @param bool $ignoreNoTransaction If `true` and no transaction is open,
     * return without throwing an exception. Recommended in `catch` blocks where
     * a transaction may or may not have been successfully opened.
     * @return $this
     * @throws LogicException if no transaction is open.
     */
    final protected function rollbackTransaction(bool $ignoreNoTransaction = false)
    {
        if ($this->Db && !$this->IsTransactionOpen) {
            if ($ignoreNoTransaction) {
                return $this;
            }

            throw new LogicException('No transaction open');
        }

        $this->db()->exec('ROLLBACK');
        $this->IsTransactionOpen = false;

        return $this;
    }

    /**
     * BEGIN a transaction, run a callback and COMMIT or ROLLBACK as needed
     *
     * A rollback is attempted if an exception is caught, otherwise the
     * transaction is committed.
     *
     * @return mixed The callback's return value.
     * @throws LogicException if a transaction is already open.
     */
    final protected function callInTransaction(callable $callback)
    {
        if ($this->Db && $this->IsTransactionOpen) {
            throw new LogicException('Transaction already open');
        }

        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commitTransaction();
        } catch (Throwable $ex) {
            $this->rollbackTransaction();

            throw $ex;
        }

        return $result;
    }

    /**
     * Throw an exception if the SQLite3 library doesn't support UPSERT syntax
     *
     * @link https://www.sqlite.org/lang_UPSERT.html
     *
     * @return $this
     */
    final protected function requireUpsert()
    {
        if (SQLite3::version()['versionNumber'] >= 3024000) {
            return $this;
        }

        throw new InvalidRuntimeConfigurationException('SQLite 3.24 or above required');
    }

    /**
     * Throw an exception if the database is not open
     */
    final protected function assertIsOpen(): void
    {
        if (!$this->Db) {
            throw new LogicException('No database open');
        }
    }
}
