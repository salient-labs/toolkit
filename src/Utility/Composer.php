<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Lkrms\Facade\File;
use RuntimeException;

/**
 * Get information about the root package and installed dependencies
 *
 */
final class Composer
{
    /**
     * Return true if require-dev packages are installed
     *
     * Returns `false` if `--no-dev` was passed to `composer install`, e.g. with
     * the following packaging command:
     *
     * ```shell
     * composer install --no-dev --no-plugins --optimize-autoloader --classmap-authoritative
     * ```
     *
     */
    public function hasDevDependencies(): bool
    {
        return $this->getRootPackageValue('dev');
    }

    /**
     * Get the name of the root package
     *
     */
    public function getRootPackageName(): string
    {
        return $this->getRootPackageValue('name');
    }

    /**
     * Get the commit reference of the root package, if known
     *
     */
    public function getRootPackageReference(?int $abbrev = 8): ?string
    {
        return $this->formatReference(
            $this->getRootPackageValue('reference'),
            $abbrev
        );
    }

    /**
     * Get the version of the root package
     *
     * If Composer returns a version like `dev-<branch>`, `@<reference>` is
     * added after the branch name. Otherwise, if `$withReference` is `true` and
     * a commit reference is available, `-<reference>` is added to the version
     * number.
     *
     * @param bool $pretty If `true`, return the original version number, e.g.
     * `v0.3.1` instead of `0.3.1.0`.
     */
    public function getRootPackageVersion(bool $pretty = false, bool $withReference = false): string
    {
        return $this->formatVersion(
            $this->getRootPackageValue($pretty ? 'pretty_version' : 'version'),
            $withReference,
            fn() => $this->getRootPackageReference()
        );
    }

    /**
     * Get the canonical path of the root package
     *
     */
    public function getRootPackagePath(): string
    {
        $path = $this->getRootPackageValue('install_path');
        if (($realpath = File::realpath($path)) === false) {
            throw new RuntimeException('Directory not found: ' . $path);
        }

        return $realpath;
    }

    /**
     * Get the commit reference of an installed package, if known
     *
     */
    public function getPackageReference(string $package = 'lkrms/util', ?int $abbrev = 8): ?string
    {
        if (!InstalledVersions::isInstalled($package)) {
            return null;
        }

        return $this->formatReference(
            InstalledVersions::getReference($package),
            $abbrev
        );
    }

    /**
     * Get the version of an installed package
     *
     * If Composer returns a version like `dev-<branch>`, `@<reference>` is
     * added after the branch name. Otherwise, if `$withReference` is `true` and
     * a commit reference is available, `-<reference>` is added to the version
     * number.
     *
     * Returns `null` if `$package` is not installed.
     *
     * @param bool $pretty If `true`, return the original version number, e.g.
     * `v0.3.1` instead of `0.3.1.0`.
     */
    public function getPackageVersion(string $package = 'lkrms/util', bool $pretty = false, bool $withReference = false): ?string
    {
        if (!InstalledVersions::isInstalled($package)) {
            return null;
        }

        return $this->formatVersion(
            $pretty
                ? InstalledVersions::getPrettyVersion($package)
                : InstalledVersions::getVersion($package),
            $withReference,
            fn() => $this->getPackageReference($package)
        );
    }

    /**
     * Get the canonical path of an installed package
     *
     * Returns `null` if `$package` is not installed.
     *
     */
    public function getPackagePath(string $package = 'lkrms/util'): ?string
    {
        if (!InstalledVersions::isInstalled($package)) {
            return null;
        }

        return InstalledVersions::getInstallPath($package);
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
     * Use ClassLoader to resolve a namespace to a path
     *
     * Returns `null` if `$namespace` doesn't match a PSR-4 mapping defined by
     * the root package or an installed dependency in one of their
     * `composer.json` files.
     *
     * The path returned need not exist.
     */
    public function getNamespacePath(string $namespace): ?string
    {
        $namespace = trim($namespace, '\\');
        $prefixes  = [];
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            // If multiple loaders return the same prefix, prefer the first one
            $prefixes = array_merge($loader->getPrefixesPsr4(), $prefixes);
        }
        // Sort prefixes from longest to shortest
        uksort($prefixes, fn($p1, $p2) => strlen($p2) <=> strlen($p1));

        foreach ($prefixes as $prefix => $dirs) {
            if (!strcasecmp(substr($namespace . '\\', 0, strlen($prefix)), $prefix)) {
                foreach ((array) $dirs as $dir) {
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

    /**
     * @return string|string[]|bool|null
     */
    private function getRootPackageValue(string $key)
    {
        $values = InstalledVersions::getRootPackage();
        if (!array_key_exists($key, $values)) {
            throw new RuntimeException("Value not found in root package: $key");
        }

        return $values[$key];
    }

    private function formatVersion(string $version, bool $withReference, callable $refCallback): string
    {
        if (strpos($version, 'dev-') === 0 && ($ref = $refCallback())) {
            return $version . "@$ref";
        }
        if ($withReference && ($ref = $refCallback())) {
            return $version . "-$ref";
        }

        return $version;
    }

    private function formatReference(?string $ref, ?int $abbrev): ?string
    {
        return is_string($ref)
            ? (($abbrev ?: 0) < 4 ? $ref : substr($ref, 0, $abbrev))
            : null;
    }
}
