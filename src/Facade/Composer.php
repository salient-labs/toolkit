<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;

/**
 * A facade for \Lkrms\Utility\Composer
 *
 * @method static \Lkrms\Utility\Composer load() Load and return an instance of the underlying `Composer` class
 * @method static \Lkrms\Utility\Composer getInstance() Return the underlying `Composer` instance
 * @method static bool isLoaded() Return true if an underlying `Composer` instance has been loaded
 * @method static void unload() Clear the underlying `Composer` instance
 * @method static string|null getClassPath(string $class) Use ClassLoader to find the file where a class is defined (see {@see \Lkrms\Utility\Composer::getClassPath()})
 * @method static string|null getNamespacePath(string $namespace) Use ClassLoader's PSR-4 prefixes to resolve a namespace to a path (see {@see \Lkrms\Utility\Composer::getNamespacePath()})
 * @method static string|null getPackagePath(string $name = 'lkrms/util') See {@see \Lkrms\Utility\Composer::getPackagePath()}
 * @method static string|null getPackageVersion(string $name = 'lkrms/util') See {@see \Lkrms\Utility\Composer::getPackageVersion()}
 * @method static string getRootPackageName() See {@see \Lkrms\Utility\Composer::getRootPackageName()}
 * @method static string getRootPackagePath() See {@see \Lkrms\Utility\Composer::getRootPackagePath()}
 * @method static string getRootPackageVersion() See {@see \Lkrms\Utility\Composer::getRootPackageVersion()}
 *
 * @uses \Lkrms\Utility\Composer
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Composer' --generate='Lkrms\Facade\Composer'
 */
final class Composer extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return \Lkrms\Utility\Composer::class;
    }
}
