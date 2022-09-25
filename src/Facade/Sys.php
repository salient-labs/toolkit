<?php

declare(strict_types=1);

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
 * @method static int getMemoryLimit() See {@see System::getMemoryLimit()}
 * @method static int getMemoryUsage() See {@see System::getMemoryUsage()}
 * @method static int getMemoryUsagePercent() See {@see System::getMemoryUsagePercent()}
 * @method static string getProgramName() See {@see System::getProgramName()}
 *
 * @uses System
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\System' --generate='Lkrms\Facade\Sys'
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
