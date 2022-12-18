<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\System;

/**
 * A facade for \Lkrms\Utility\System
 *
 * @method static System load() Load and return an instance of the underlying System class
 * @method static System getInstance() Return the underlying System instance
 * @method static bool isLoaded() Return true if an underlying System instance has been loaded
 * @method static void unload() Clear the underlying System instance
 * @method static int[] getCpuUsage() Get user and system CPU times for the current run, in microseconds (see {@see System::getCpuUsage()})
 * @method static int getMemoryLimit() Get the configured memory_limit in bytes (see {@see System::getMemoryLimit()})
 * @method static int getMemoryUsage() Get the current memory usage of the script in bytes (see {@see System::getMemoryUsage()})
 * @method static int getMemoryUsagePercent() Get the current memory usage of the script as a percentage of the memory_limit (see {@see System::getMemoryUsagePercent()})
 * @method static int getPeakMemoryUsage() Get the peak memory usage of the script in bytes (see {@see System::getPeakMemoryUsage()})
 * @method static string getProgramBasename(string ...$suffixes) Return the basename of the file used to run the script (see {@see System::getProgramBasename()})
 * @method static string getProgramName(?string $basePath = null) Get the filename used to run the script (see {@see System::getProgramName()})
 * @method static array getTimers(bool $includeRunning = true, ?string $type = null) Get the elapsed milliseconds and start count for timers started in the current run (see {@see System::getTimers()})
 * @method static bool sqliteHasUpsert() Return true if the SQLite3 library supports UPSERT syntax (see {@see System::sqliteHasUpsert()})
 * @method static void startTimer(string $name, string $type = 'general') Start a timer using the system's high-resolution time (see {@see System::startTimer()})
 * @method static float stopTimer(string $name, string $type = 'general') Stop a timer and return the elapsed milliseconds (see {@see System::stopTimer()})
 *
 * @uses System
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\System' 'Lkrms\Facade\Sys'
 */
final class Sys extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return System::class;
    }
}
