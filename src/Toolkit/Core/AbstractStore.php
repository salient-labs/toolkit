<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\FacadeAwareInterface;
use Salient\Contract\Core\FacadeInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\UnloadsFacades;
use Salient\Core\Exception\LogicException;
use Salient\Core\Utility\Exception\InvalidRuntimeConfigurationException;
use Salient\Core\Utility\File;
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
     * Open the database, creating it if necessary
     *
     * @param string $filename Use `':memory:'` to create a temporary database,
     * otherwise `$filename` is created with file mode `0600` if it doesn't
     * exist. Its parent directory is created with mode `0700` if it doesn't
     * exist.
     * @return $this
     * @throws LogicException if the database is already open.
     */
    final protected function openDb(string $filename, ?string $query = null)
    {
        if ($this->Db) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Database already open');
            // @codeCoverageIgnoreEnd
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

        if ($query !== null) {
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
     * Close the database and unload any facades where the store is the
     * underlying instance
     *
     * @return $this
     */
    public function close()
    {
        return $this->closeDb();
    }

    /**
     * If the database is open, close it, and unload any facades where the store
     * is the underlying instance
     *
     * @return $this
     */
    final protected function closeDb()
    {
        if (!$this->Db) {
            // Necessary because the database may not have been opened yet
            $this->unloadFacades();
            return $this;
        }

        $this->Db->close();
        $this->Db = null;
        unset($this->Filename);
        $this->IsTransactionOpen = false;

        $this->unloadFacades();

        return $this;
    }

    /**
     * Override to perform an action whenever the underlying SQLite3 instance is
     * accessed
     *
     * Called once per call to {@see AbstractStore::db()}. Use
     * {@see AbstractStore::safeCheck()} to prevent recursion if necessary.
     *
     * @return $this
     */
    protected function check()
    {
        return $this;
    }

    /**
     * Check if the database is open
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
     * Check if safeCheck() is currently running
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
     * Get the underlying SQLite3 instance
     *
     * @throws LogicException if the database is not open.
     */
    final protected function db(): SQLite3
    {
        $this->assertIsOpen();

        if ($this->IsCheckRunning) {
            return $this->Db;
        }

        $this->safeCheck();
        $this->assertIsOpen();
        return $this->Db;
    }

    /**
     * BEGIN a transaction
     *
     * @return $this
     * @throws LogicException if a transaction is already open.
     */
    final protected function beginTransaction()
    {
        $this->assertTransactionNotOpen();

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
        if (!$this->IsTransactionOpen) {
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
     * return without throwing an exception. Useful in `catch` blocks where a
     * transaction may or may not have been successfully opened.
     * @return $this
     * @throws LogicException if no transaction is open.
     */
    final protected function rollbackTransaction(bool $ignoreNoTransaction = false)
    {
        if (!$this->IsTransactionOpen) {
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
        $this->assertTransactionNotOpen();

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
     * Throw an exception if the underlying SQLite3 library doesn't support
     * UPSERT queries
     *
     * @link https://www.sqlite.org/lang_UPSERT.html
     */
    final protected function assertCanUpsert(): void
    {
        if (SQLite3::version()['versionNumber'] < 3024000) {
            // @codeCoverageIgnoreStart
            throw new InvalidRuntimeConfigurationException('SQLite 3.24 or above required');
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @phpstan-assert !null $this->Db
     */
    final protected function assertIsOpen(): void
    {
        if (!$this->Db) {
            // @codeCoverageIgnoreStart
            throw new LogicException('No database open');
            // @codeCoverageIgnoreEnd
        }
    }

    private function assertTransactionNotOpen(): void
    {
        if ($this->IsTransactionOpen) {
            throw new LogicException('Transaction already open');
        }
    }
}
