<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\System;

/**
 * A facade for System
 *
 * @method static int getMemoryLimit()
 * @method static int getMemoryUsage()
 * @method static int getMemoryUsagePercent()
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
