<?php declare(strict_types=1);

namespace Lkrms\Db;

use Lkrms\Concept\ConvertibleEnumeration;
use UnexpectedValueException;

/**
 * Database connection drivers
 *
 */
final class DbDriver extends ConvertibleEnumeration
{
    /**
     * IBM Db2
     */
    public const DB2 = 0;

    /**
     * Microsoft SQL Server
     */
    public const MSSQL = 1;

    /**
     * MySQL or MariaDB
     */
    public const MYSQL = 2;

    /**
     * SQLite
     */
    public const SQLITE = 3;

    private static $AdodbDriverMap = [
        self::DB2 => 'db2',
        self::MSSQL => 'mssqlnative',
        self::MYSQL => 'mysqli',
        self::SQLITE => 'sqlite3',
    ];

    /**
     * @internal
     */
    public static function toAdodbDriver(int $driver): string
    {
        if (is_null($adodbDriver = self::$AdodbDriverMap[$driver] ?? null)) {
            throw new UnexpectedValueException("Invalid DbDriver: $driver");
        }

        return $adodbDriver;
    }
}
