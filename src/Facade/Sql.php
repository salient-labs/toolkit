<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\SqlQuery;

/**
 * A facade for \Lkrms\Utility\Sql
 *
 * @method static \Lkrms\Utility\Sql load() Load and return an instance of the underlying Sql class
 * @method static \Lkrms\Utility\Sql getInstance() Return the underlying Sql instance
 * @method static bool isLoaded() Return true if an underlying Sql instance has been loaded
 * @method static void unload() Clear the underlying Sql instance
 * @method static void valueInList(SqlQuery $query, string $name, mixed ...$value) Add "<name> IN (<value>[,<value>])" to a SQL query unless a list of values is empty (see {@see \Lkrms\Utility\Sql::valueInList()})
 *
 * @uses \Lkrms\Utility\Sql
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Sql' --generate='Lkrms\Facade\Sql'
 */
final class Sql extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return \Lkrms\Utility\Sql::class;
    }
}
