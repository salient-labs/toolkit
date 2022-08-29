<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\SqlHelpers;

/**
 * A facade for SqlHelpers
 *
 * @method static mixed valueInList(string $field, callable $param, array &$where, array &$var, mixed ...$list) Add "<name> IN (<value>[,<value>])" unless a list of values is empty
 *
 * @uses SqlHelpers
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\SqlHelpers' --generate='Lkrms\Facade\Sql'
 */
final class Sql extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return SqlHelpers::class;
    }
}
