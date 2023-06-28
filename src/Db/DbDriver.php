<?php declare(strict_types=1);

namespace Lkrms\Db;

use Lkrms\Concept\ReflectiveEnumeration;
use LogicException;

/**
 * Database connection drivers
 *
 * @extends ReflectiveEnumeration<int>
 */
final class DbDriver extends ReflectiveEnumeration
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

    /**
     * @var array<int,string>
     */
    private const ADODB_DRIVER_MAP = [
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
        if (($adodbDriver = self::ADODB_DRIVER_MAP[$driver] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($driver) is invalid: %d', $driver)
            );
        }

        return $adodbDriver;
    }
}
