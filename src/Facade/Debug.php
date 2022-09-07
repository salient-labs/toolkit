<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Debugging;

/**
 * A facade for \Lkrms\Utility\Debugging
 *
 * @method static Debugging load() Load and return an instance of the underlying Debugging class
 * @method static Debugging getInstance() Return the underlying Debugging instance
 * @method static bool isLoaded() Return true if an underlying Debugging instance has been loaded
 * @method static void unload() Clear the underlying Debugging instance
 * @method static array getCaller(int $depth = 0) Use debug_backtrace to get information about the (caller's) caller (see {@see Debugging::getCaller()})
 * @method static void setFacade(string $name) Called immediately after instantiation by a facade (see {@see Debugging::setFacade()})
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
