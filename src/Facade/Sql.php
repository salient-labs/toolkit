<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\SqlQuery;

/**
 * A facade for \Lkrms\Utility\Sql
 *
 * @method static void valueInList(SqlQuery $query, string $name, mixed ...$value) Add "<name> IN (<value>[,<value>])" to a SQL query unless a list of values is empty
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
