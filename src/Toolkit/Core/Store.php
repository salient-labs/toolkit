<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Instantiable;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\FacadeAwareTrait;
use Salient\Core\Internal\StoreState;
use Salient\Utility\Exception\InvalidRuntimeConfigurationException;
use Salient\Utility\File;
use LogicException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;
use Throwable;

/**
 * Base class for SQLite-backed stores
 *
 * @api
 *
 * @implements FacadeAwareInterface<static>
 */
abstract class Store implements
    FacadeAwareInterface,
    Instantiable,
    Unloadable
{
    /** @use FacadeAwareTrait<static> */
    use FacadeAwareTrait;

    private ?StoreState $State = null;
    private bool $IsCheckRunning = false;

    private function __clone() {}

    /**
     * Open the database, creating it if necessary
     *
     * @param string $filename Use `":memory:"` to create an in-memory database,
     * or an empty string to create a temporary database on the filesystem.
     * Otherwise, `$filename` is created with file mode `0600` if it doesn't
     * exist. Its parent directory is created with mode `0700` if it doesn't
     * exist.
     * @return $this
     * @throws LogicException if the database is already open.
     */
    final protected function openDb(string $filename, ?string $query = null)
    {
        if ($this->isOpen()) {
            // @codeCoverageIgnoreStart
            throw new LogicException('Database already open');
            // @codeCoverageIgnoreEnd
        }

        $isTemporary = $filename === '' || $filename === ':memory:';
        if (!$isTemporary) {
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

        $this->State ??= new StoreState();
        $this->State->Db = $db;
        $this->State->Filename = $filename;
        $this->State->IsTemporary = $isTemporary;
        $this->State->HasTransaction = false;
        $this->State->IsOpen = true;

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
     */
    public function close(): void
    {
        $this->closeDb();
    }

    /**
     * If the database is open, close it, and unload any facades where the store
     * is the underlying instance
     */
    final protected function closeDb(): void
    {
        if (!$this->isOpen()) {
            // Necessary because the database may not have been opened yet
            $this->unloadFacades();
            return;
        }

        $this->State->Db->close();
        $this->State->IsOpen = false;
        unset($this->State->Db);
        unset($this->State->Filename);
        $this->State->IsTemporary = false;
        $this->State->HasTransaction = false;

        $this->unloadFacades();
    }

    /**
     * Close the store without closing the database by detaching the database
     * from the store, and unload any facades where the store is the underlying
     * instance
     */
    public function detach(): void
    {
        $this->detachDb();
    }

    /**
     * Detach the database from the store and unload any facades where the store
     * is the underlying instance
     */
    final protected function detachDb(): void
    {
        $this->State = null;
        $this->unloadFacades();
    }

    /**
     * Override to perform an action whenever the underlying SQLite3 instance is
     * accessed
     *
     * Called once per call to {@see Store::db()}. Use {@see Store::safeCheck()}
     * to prevent recursion if necessary.
     *
     * @return $this
     */
    protected function check()
    {
        return $this;
    }

    /**
     * Check if the database is open
     *
     * @phpstan-assert-if-true !null $this->State
     */
    final public function isOpen(): bool
    {
        return $this->State && $this->State->IsOpen;
    }

    /**
     * Check if the store is backed by a temporary or in-memory database
     *
     * @throws LogicException if the database is not open.
     */
    final public function isTemporary(): bool
    {
        $this->assertIsOpen();

        return $this->State->IsTemporary;
    }

    /**
     * Get the filename of the database
     *
     * @throws LogicException if the database is not open.
     */
    final public function getFilename(): string
    {
        $this->assertIsOpen();

        return $this->State->Filename;
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
     * {@see Store::db()} calls {@see Store::check()} via
     * {@see Store::safeCheck()} to prevent recursion when {@see Store::check()}
     * calls {@see Store::db()}.
     *
     * If {@see Store::check()} may be called directly, it should call itself
     * via {@see Store::safeCheck()}, for example:
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
            return $this->State->Db;
        }

        $this->safeCheck();
        $this->assertIsOpen();
        return $this->State->Db;
    }

    /**
     * Prepare a SQL statement for execution
     */
    final protected function prepare(string $query): SQLite3Stmt
    {
        $stmt = $this->db()->prepare($query);
        assert($stmt !== false);
        return $stmt;
    }

    /**
     * Execute a prepared statement
     */
    final protected function execute(SQLite3Stmt $stmt): SQLite3Result
    {
        $result = $stmt->execute();
        assert($result !== false);
        return $result;
    }

    /**
     * Check if a transaction has been started
     */
    final protected function hasTransaction(): bool
    {
        $this->assertIsOpen();

        return $this->State->HasTransaction;
    }

    /**
     * BEGIN a transaction
     *
     * @return $this
     * @throws LogicException if a transaction has already been started.
     */
    final protected function beginTransaction()
    {
        $this->assertIsOpen();

        if ($this->State->HasTransaction) {
            throw new LogicException('Transaction already started');
        }

        $this->db()->exec('BEGIN IMMEDIATE');
        $this->State->HasTransaction = true;

        return $this;
    }

    /**
     * COMMIT a transaction
     *
     * @return $this
     * @throws LogicException if no transaction has been started.
     */
    final protected function commitTransaction()
    {
        $this->assertIsOpen();

        if (!$this->State->HasTransaction) {
            throw new LogicException('No transaction started');
        }

        $this->db()->exec('COMMIT');
        $this->State->HasTransaction = false;

        return $this;
    }

    /**
     * ROLLBACK a transaction
     *
     * @param bool $ignoreNoTransaction If `true` and no transaction has been
     * started, return without throwing an exception. Useful in `catch` blocks
     * where a transaction may or may not have been started.
     * @return $this
     * @throws LogicException if no transaction has been started.
     */
    final protected function rollbackTransaction(bool $ignoreNoTransaction = false)
    {
        $this->assertIsOpen();

        if (!$this->State->HasTransaction) {
            if ($ignoreNoTransaction) {
                return $this;
            }
            throw new LogicException('No transaction started');
        }

        $this->db()->exec('ROLLBACK');
        $this->State->HasTransaction = false;

        return $this;
    }

    /**
     * BEGIN a transaction, run a callback and COMMIT or ROLLBACK as needed
     *
     * A rollback is attempted if an exception is caught, otherwise the
     * transaction is committed.
     *
     * @template T
     *
     * @param callable(): T $callback
     * @return T
     * @throws LogicException if a transaction has already been started.
     */
    final protected function callInTransaction(callable $callback)
    {
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
     * Throw an exception if the database is not open
     *
     * @phpstan-assert !null $this->State
     */
    final protected function assertIsOpen(): void
    {
        if (!$this->State || !$this->State->IsOpen) {
            // @codeCoverageIgnoreStart
            throw new LogicException('No database open');
            // @codeCoverageIgnoreEnd
        }
    }
}
