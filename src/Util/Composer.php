<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Composer\InstalledVersions;
use Lkrms\Core\Utility;
use RuntimeException;

/**
 * Get information about installed Composer packages
 *
 */
final class Composer extends Utility
{
    private static function getRootPackageValue(string $value): string
    {
        $value = InstalledVersions::getRootPackage()[$value] ?? null;

        if (is_null($value))
        {
            throw new RuntimeException("Root package $value not found");
        }

        return $value;
    }

    public static function getRootPackageName(): string
    {
        return self::getRootPackageValue("name");
    }

    public static function getRootPackageVersion(): string
    {
        if (preg_match('/^dev-/', $version = self::getRootPackageValue("version")))
        {
            $version = substr(self::getRootPackageValue("reference"), 0, 7);
        }

        return $version;
    }

    public static function getRootPackagePath(): string
    {
        $path = self::getRootPackageValue("install_path");

        if (($realpath = realpath($path)) === false)
        {
            throw new RuntimeException("Directory not found: " . $path);
        }

        return $realpath;
    }

    public static function getPackageVersion(string $name = "lkrms/util"): ?string
    {
        if (!InstalledVersions::isInstalled($name))
        {
            return null;
        }

        if (preg_match('/^dev-/', $version = InstalledVersions::getVersion($name)))
        {
            $version = substr(InstalledVersions::getReference($name), 0, 7);
        }

        return $version;
    }
}
