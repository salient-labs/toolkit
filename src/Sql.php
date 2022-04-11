<?php

declare(strict_types=1);

namespace Lkrms;

/**
 * SQL helpers
 *
 * @package Lkrms
 */
class Sql
{
    /**
     * Add "<name> IN (<value>[,<value>])" unless a list of values is empty
     *
     * @param string $field The field to test.
     * @param callable $param e.g. use `[$db, "param"]` to add
     * `$db->param("<name>")` to the SQL for each value in `$list`.
     * @param array $where A list of WHERE clauses.
     * @param array $var An associative array of query variables.
     * @param mixed $list Zero or more values.
     */
    public static function valueInList(string $field, callable $param, ?array & $where, ?array & $var, ...$list)
    {
        if (is_null($where))
        {
            $where = [];
        }

        if (is_null($var))
        {
            $var = [];
        }

        if (!count($list))
        {
            return;
        }

        $expr = [];

        foreach ($list as $value)
        {
            $name       = "param_" . count($var);
            $expr[]     = $param($name);
            $var[$name] = $value;
        }

        $where[] = "$field IN (" . implode(",", $expr) . ")";
    }
}

