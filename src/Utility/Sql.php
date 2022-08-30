<?php

declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Support\SqlQuery;

/**
 * Work with SQL queries
 *
 */
final class Sql
{
    /**
     * Add "<name> IN (<value>[,<value>])" to a SQL query unless a list of
     * values is empty
     *
     */
    public function valueInList(SqlQuery $query, string $name, ...$value): void
    {
        if (!count($value))
        {
            return;
        }

        $expr = [];
        foreach ($value as $_value)
        {
            $expr[] = $query->addParam("param_" . count($query->Values), $_value);
        }
        $query->Where[] = "$name IN (" . implode(",", $expr) . ")";
    }

}
