<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;

/**
 * A facade for \Lkrms\Utility\Composer
 *
 * @method static \Lkrms\Utility\Composer load() Load and return an instance of the underlying Composer class
 * @method static \Lkrms\Utility\Composer getInstance() Return the underlying Composer instance
 * @method static bool isLoaded() Return true if an underlying Composer instance has been loaded
 * @method static void unload() Clear the underlying Composer instance
 * @method static string|null getClassPath(string $class) Use ClassLoader to find the file where a class is defined (see {@see \Lkrms\Utility\Composer::getClassPath()})
 * @method static string|null getNamespacePath(string $namespace) Use ClassLoader to resolve a namespace to a path (see {@see \Lkrms\Utility\Composer::getNamespacePath()})
 * @method static string|null getPackagePath(string $package = 'lkrms/util') Get the canonical path of an installed package (see {@see \Lkrms\Utility\Composer::getPackagePath()})
 * @method static string|null getPackageReference(string $package = 'lkrms/util', ?int $abbrev = 8) Get the commit reference of an installed package, if known
 * @method static string|null getPackageVersion(string $package = 'lkrms/util', bool $pretty = false, bool $withReference = false) Get the version of an installed package (see {@see \Lkrms\Utility\Composer::getPackageVersion()})
 * @method static string getRootPackageName() Get the name of the root package
 * @method static string getRootPackagePath() Get the canonical path of the root package
 * @method static string|null getRootPackageReference(?int $abbrev = 8) Get the commit reference of the root package, if known
 * @method static string getRootPackageVersion(bool $pretty = false, bool $withReference = false) Get the version of the root package (see {@see \Lkrms\Utility\Composer::getRootPackageVersion()})
 * @method static bool hasDevDependencies() Return true if require-dev packages are installed (see {@see \Lkrms\Utility\Composer::hasDevDependencies()})
 *
 * @uses \Lkrms\Utility\Composer
 * @extends Facade<\Lkrms\Utility\Composer>
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Composer' 'Lkrms\Facade\Composer'
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
