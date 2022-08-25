<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Debugging;

/**
 * A facade for Debugging
 *
 * @method static array getCaller(int $depth = 0) Use debug_backtrace to get information about the (caller's) caller
 * @method static mixed setFacade(string $name)
 *
 * @uses Debugging
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Debugging' --generate='Lkrms\Facade\Debug'
 */
final class Debug extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Debugging::class;
    }
}
