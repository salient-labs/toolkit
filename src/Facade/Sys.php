<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\System;

/**
 * A facade for \Lkrms\Utility\System
 *
 * @method static System load() Load and return an instance of the underlying System class
 * @method static System getInstance() Get the underlying System instance
 * @method static bool isLoaded() True if an underlying System instance has been loaded
 * @method static void unload() Clear the underlying System instance
 * @method static string escapeCommand(string[] $args) Get a command string with arguments escaped for this platform's shell (see {@see System::escapeCommand()})
 * @method static array{int,int} getCpuUsage() Get user and system CPU times for the current run, in microseconds (see {@see System::getCpuUsage()})
 * @method static string getCwd() Get the current working directory without resolving symbolic links
 * @method static int getMemoryLimit() Get the configured memory_limit, in bytes
 * @method static int getMemoryUsage() Get the current memory usage of the script, in bytes
 * @method static int getMemoryUsagePercent() Get the current memory usage of the script as a percentage of the memory_limit
 * @method static int getPeakMemoryUsage() Get the peak memory usage of the script, in bytes
 * @method static string getProgramBasename(string ...$suffixes) Get the basename of the file used to run the script (see {@see System::getProgramBasename()})
 * @method static string getProgramName(?string $basePath = null) Get the filename used to run the script (see {@see System::getProgramName()})
 * @method static bool handleExitSignals() Handle SIGINT and SIGTERM to make a clean exit from the running script (see {@see System::handleExitSignals()})
 * @method static bool sqliteHasUpsert() True if the SQLite3 library supports UPSERT syntax (see {@see System::sqliteHasUpsert()})
 *
 * @uses System
 *
 * @extends Facade<System>
 */
final class Sys extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getServiceName(): string
    {
        return System::class;
    }
}
