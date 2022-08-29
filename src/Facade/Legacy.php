<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\LegacyShims;

/**
 * A facade for LegacyShims
 *
 * @method static void registerAutoloader() Register an autoloader for renamed classes
 *
 * @uses LegacyShims
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\LegacyShims' --generate='Lkrms\Facade\Legacy'
 */
final class Legacy extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return LegacyShims::class;
    }
}
