<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use RuntimeException;

/**
 * Get information about installed packages
 *
 */
final class Composer
{
    private function getRootPackageValue(string $key): string
    {
        $value = InstalledVersions::getRootPackage()[$key] ?? null;

        if (is_null($value)) {
            throw new RuntimeException("Value not found: $key");
        }

        return $value;
    }

    public function getRootPackageName(): string
    {
        return $this->getRootPackageValue('name');
    }

    public function getRootPackageVersion(bool $pretty = false): string
    {
        $version = $this->getRootPackageValue($pretty ? 'pretty_version' : 'version');
        if (preg_match('/^dev-/', $version)) {
            $version = substr($this->getRootPackageValue('reference'), 0, 8);
        }

        return $version;
    }

    public function getRootPackagePath(): string
    {
        $path = $this->getRootPackageValue('install_path');

        if (($realpath = File::realpath($path)) === false) {
            throw new RuntimeException('Directory not found: ' . $path);
        }

        return $realpath;
    }

    public function getPackageVersion(string $name = 'lkrms/util', bool $pretty = false): ?string
    {
        if (!InstalledVersions::isInstalled($name)) {
            return null;
        }

        $version = $pretty
            ? InstalledVersions::getPrettyVersion($name)
            : InstalledVersions::getVersion($name);
        if (preg_match('/^dev-/', $version)) {
            $version = substr(InstalledVersions::getReference($name), 0, 8);
        }

        return $version;
    }

    public function getPackagePath(string $name = 'lkrms/util'): ?string
    {
        if (!InstalledVersions::isInstalled($name)) {
            return null;
        }

        return InstalledVersions::getInstallPath($name);
    }

    /**
     * Use ClassLoader to find the file where a class is defined
     *
     * Returns `null` if `$class` doesn't exist.
     *
     * @see Composer::getNamespacePath()
     */
    public function getClassPath(string $class): ?string
    {
        $class = ltrim($class, '\\');

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            if ($file = $loader->findFile($class)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Use ClassLoader's PSR-4 prefixes to resolve a namespace to a path
     *
     * Returns `null` if `$namespace` or its base directory aren't configured or
     * don't exist. The path returned may not exist.
     */
    public function getNamespacePath(string $namespace): ?string
    {
        $namespace = ltrim($namespace, '\\');
        $prefixes  = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            // If multiple loaders return the same prefix, prefer the first one
            $prefixes = array_merge($loader->getPrefixesPsr4(), $prefixes);
        }

        // Sort prefixes from longest to shortest
        uksort($prefixes, fn($p1, $p2) => strlen($p2) <=> strlen($p1));

        foreach ($prefixes as $prefix => $dirs) {
            if (substr($namespace . '\\', 0, strlen($prefix)) == $prefix) {
                foreach (Convert::toArray($dirs) as $dir) {
                    if (($dir = File::realpath($dir)) && is_dir($dir)) {
                        if ($subdir = strtr(substr($namespace, strlen($prefix)), '\\', DIRECTORY_SEPARATOR)) {
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
