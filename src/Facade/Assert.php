<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\AssertionUtility;

/**
 * A facade for AssertionUtility
 *
 * @method static void argvIsRegistered()
 * @method static void localeIsUtf8()
 * @method static void notEmpty(mixed $value, ?string $name = null)
 * @method static void patternMatches(?string $value, string $pattern, ?string $name = null)
 * @method static void sapiIsCli()
 *
 * @uses AssertionUtility
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\AssertionUtility' --generate='Lkrms\Facade\Assert'
 */
final class Assert extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return AssertionUtility::class;
    }
}
