<?php declare(strict_types=1);

namespace Lkrms\Db;

use Salient\Core\AbstractReflectiveEnumeration;
use LogicException;

/**
 * Database connection drivers
 *
 * @extends AbstractReflectiveEnumeration<int>
 */
final class DbDriver extends AbstractReflectiveEnumeration
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
     * @var array<DbDriver::*,string>
     */
    private static $AdodbDriverMap = [
        self::DB2 => 'db2',
        self::MSSQL => 'mssqlnative',
        self::MYSQL => 'mysqli',
        self::SQLITE => 'sqlite3',
    ];

    /**
     * @internal
     *
     * @param DbDriver::* $driver
     */
    public static function toAdodbDriver(int $driver): string
    {
        if (($adodbDriver = self::$AdodbDriverMap[$driver] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($driver) is invalid: %d', $driver)
            );
        }

        return $adodbDriver;
    }
}
