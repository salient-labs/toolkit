<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Lkrms\Concept\Utility;
use RuntimeException;

/**
 * Get information about installed packages
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

    /**
     * Use ClassLoader to find the file where a class is defined
     *
     * @param string $class
     * @return null|string
     */
    public static function getClassPath(string $class): ?string
    {
        $class = trim($class, "\\");

        foreach (ClassLoader::getRegisteredLoaders() as $loader)
        {
            if ($file = $loader->findFile($class))
            {
                return $file;
            }
        }

        return null;
    }

    /**
     * Use ClassLoader's PSR-4 prefixes to resolve a namespace to a path
     *
     * @param string $namespace
     * @return null|string
     */
    public static function getNamespacePath(string $namespace): ?string
    {
        $namespace = trim($namespace, "\\");
        $prefixes  = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader)
        {
            $prefixes = array_merge($prefixes, $loader->getPrefixesPsr4());
        }

        uksort($prefixes, function ($p1, $p2)
        {
            $l1 = strlen($p1);
            $l2 = strlen($p2);

            return ($l1 === $l2) ? 0 : ($l1 < $l2 ? 1 : - 1);
        });

        foreach ($prefixes as $prefix => $dirs)
        {
            if (substr($namespace . "\\", 0, strlen($prefix)) == $prefix)
            {
                foreach (Convert::toArray($dirs) as $dir)
                {
                    if (($dir = realpath($dir)) && is_dir($dir))
                    {
                        if ($subdir = strtr(substr($namespace, strlen($prefix)), "\\", DIRECTORY_SEPARATOR))
                        {
                            return $dir . DIRECTORY_SEPARATOR . $subdir;
                        }

                        return $dir;
                    }
                }
            }
        }

        return null;
    }
}
