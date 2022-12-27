<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Assertions;

/**
 * A facade for \Lkrms\Utility\Assertions
 *
 * @method static Assertions load() Load and return an instance of the underlying Assertions class
 * @method static Assertions getInstance() Return the underlying Assertions instance
 * @method static bool isLoaded() Return true if an underlying Assertions instance has been loaded
 * @method static void unload() Clear the underlying Assertions instance
 * @method static void argvIsRegistered() See {@see Assertions::argvIsRegistered()}
 * @method static void localeIsUtf8() See {@see Assertions::localeIsUtf8()}
 * @method static void notEmpty($value, ?string $name = null) See {@see Assertions::notEmpty()}
 * @method static void patternMatches(?string $value, string $pattern, ?string $name = null) See {@see Assertions::patternMatches()}
 * @method static void sapiIsCli() See {@see Assertions::sapiIsCli()}
 *
 * @uses Assertions
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Assertions' 'Lkrms\Facade\Assert'
 */
final class Assert extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Assertions::class;
    }
}
