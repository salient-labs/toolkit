<?php

declare(strict_types=1);

namespace Lkrms\Store;

use Exception;
use Lkrms\Console\Console;
use Lkrms\File;
use RuntimeException;
use SQLite3;

/**
 * Base class for SQLite-backed stores
 *
 * @package Lkrms\Service
 */
abstract class Sqlite
{
    /**
     * @var array<string,SQLite3>
     */
    private static $Db = [];

    /**
     * @var array<string,string>
     */
    private static $Filename = [];

    /**
     * @var array<string,bool>
     */
    private static $IsTransactionOpen = [];

    /**
     * Create or open a database
     *
     * @param string $filename
     */
    public static function open(string $filename = ":memory:")
    {
        self::close();

        if ($filename != ":memory:")
        {
            File::maybeCreate($filename, 0600, 0700);
        }

        $db = new SQLite3($filename);
        $db->enableExceptions();
        $db->busyTimeout(60000);
        $db->exec('PRAGMA journal_mode=WAL');
        self::$Db[static::class] = $db;
        self::$Filename[static::class] = $filename;
    }

    /**
     * If a database is open, close it
     */
    public static function close()
    {
        if (!self::isOpen())
        {
            return;
        }

        self::db()->close();
        unset(self::$Db[static::class], self::$Filename[static::class]);
    }

    /**
     * Check if a database is open
     *
     * @return bool
     */
    public static function isOpen(): bool
    {
        return isset(self::$Db[static::class]);
    }

    /**
     * Get the filename of the database
     *
     * @return null|string
     */
    public static function getFilename(): ?string
    {
        return self::$Filename[static::class] ?? null;
    }

    /**
     * Throw an exception if a database is not open
     *
     * @throws RuntimeException
     */
    protected static function assertIsOpen()
    {
        if (!self::isOpen())
        {
            throw new RuntimeException("open() must be called first");
        }
    }

    /**
     * Get the open SQLite3 instance
     *
     * Call {@see Sqlite::assertOpen()} first to ensure the return value is not
     * `null`.
     *
     * @return null|SQLite3 .
     */
    protected static function db(): ?SQLite3
    {
        return self::$Db[static::class] ?? null;
    }

    protected static function isTransactionOpen(): bool
    {
        return self::$IsTransactionOpen[static::class] ?? false;
    }

    protected static function beginTransaction()
    {
        if (self::isTransactionOpen())
        {
            Console::debug("Transaction already open");

            return;
        }

        self::db()->exec("BEGIN");
        self::$IsTransactionOpen[static::class] = true;
    }

    protected static function commitTransaction()
    {
        if (!self::isTransactionOpen())
        {
            Console::debug("No transaction open");

            return;
        }

        self::db()->exec("COMMIT");
        unset(self::$IsTransactionOpen[static::class]);
    }

    protected static function rollbackTransaction()
    {
        // Silently ignore transactionless rollback requests, e.g. when an
        // exception is caught in a Schrödinger's transaction scenario
        if (!self::isTransactionOpen())
        {
            return;
        }

        self::db()->exec("ROLLBACK");
        unset(self::$IsTransactionOpen[static::class]);
    }

    protected static function invokeInTransaction(callable $callback)
    {
        if (self::isTransactionOpen())
        {
            throw new RuntimeException("Transaction already open");
        }

        self::beginTransaction();

        try
        {
            $callback();
            self::commitTransaction();
        }
        catch (Exception $ex)
        {
            self::rollbackTransaction();
            throw $ex;
        }
    }
}

