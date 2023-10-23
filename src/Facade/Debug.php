<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Debugging;

/**
 * A facade for \Lkrms\Utility\Debugging
 *
 * @method static Debugging load() Load and return an instance of the underlying Debugging class
 * @method static Debugging getInstance() Get the underlying Debugging instance
 * @method static bool isLoaded() True if an underlying Debugging instance has been loaded
 * @method static void unload() Clear the underlying Debugging instance
 * @method static array<int|string> getCaller(int $depth = 0) Use debug_backtrace to get information about the (caller's) caller (see {@see Debugging::getCaller()})
 *
 * @uses Debugging
 *
 * @extends Facade<Debugging>
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
