<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;

/**
 * A facade for \Lkrms\Utility\Composer
 *
 * @method static string|null getClassPath(string $class) Use ClassLoader to find the file where a class is defined
 * @method static string|null getNamespacePath(string $namespace) Use ClassLoader's PSR-4 prefixes to resolve a namespace to a path
 * @method static string|null getPackagePath(string $name = 'lkrms/util')
 * @method static string|null getPackageVersion(string $name = 'lkrms/util')
 * @method static string getRootPackageName()
 * @method static string getRootPackagePath()
 * @method static string getRootPackageVersion()
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
