<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Assertions;

/**
 * A facade for \Lkrms\Utility\Assertions
 *
 * @method static Assertions load() Load and return an instance of the underlying Assertions class
 * @method static Assertions getInstance() Get the underlying Assertions instance
 * @method static bool isLoaded() True if an underlying Assertions instance has been loaded
 * @method static void unload() Clear the underlying Assertions instance
 * @method static void argvIsRegistered() A facade for Assertions::argvIsRegistered()
 * @method static void localeIsUtf8() A facade for Assertions::localeIsUtf8()
 * @method static void notEmpty(mixed $value, ?string $name = null) A facade for Assertions::notEmpty()
 * @method static void patternMatches(?string $value, string $pattern, ?string $name = null) A facade for Assertions::patternMatches()
 * @method static void sapiIsCli() A facade for Assertions::sapiIsCli()
 *
 * @uses Assertions
 *
 * @extends Facade<Assertions>
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
