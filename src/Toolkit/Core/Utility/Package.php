<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Lkrms\Exception\UnexpectedValueException;
use Salient\Core\Event\PackageDataReceivedEvent;
use Salient\Core\Facade\Event;
use Salient\Core\AbstractUtility;

/**
 * Get information from Composer's runtime API
 */
final class Package extends AbstractUtility
{
    private const SHORT_REFERENCE_LENGTH = 8;

    /**
     * True if require-dev packages are installed
     *
     * @api
     */
    public static function hasDevPackages(): bool
    {
        return self::getRootPackageValue('dev');
    }

    /**
     * Get the name of the root package
     *
     * @api
     */
    public static function name(): string
    {
        return self::getRootPackageValue('name');
    }

    /**
     * Get the commit reference of the root package, if known
     *
     * @api
     */
    public static function reference(bool $short = true): ?string
    {
        return self::formatReference(
            self::getRootPackageValue('reference'),
            $short
        );
    }

    /**
     * Get the version of the root package
     *
     * If Composer returns a version like `dev-*` or `v1.x-dev`, `@<reference>`
     * is added. Otherwise, if `$withReference` is `true` and a commit reference
     * is available, `-<reference>` is added.
     *
     * @api
     *
     * @param bool $pretty If `true`, return the original version number, e.g.
     * `v1.2.3` instead of `1.2.3.0`.
     */
    public static function version(
        bool $pretty = true,
        bool $withReference = false
    ): string {
        return self::formatVersion(
            self::getRootPackageValue($pretty ? 'pretty_version' : 'version'),
            $withReference,
            fn() => self::reference()
        );
    }

    /**
     * Get the canonical path of the root package
     *
     * @api
     */
    public static function path(): string
    {
        return File::realpath(self::getRootPackageValue('install_path'));
    }

    /**
     * Get the commit reference of an installed package, if known
     *
     * @api
     */
    public static function packageReference(
        string $package,
        bool $short = true
    ): ?string {
        if (!InstalledVersions::isInstalled($package)) {
            return null;
        }
        return self::formatReference(
            InstalledVersions::getReference($package),
            $short
        );
    }

    /**
     * Get the version of an installed package
     *
     * If Composer returns a version like `dev-*` or `v1.x-dev`, `@<reference>`
     * is added. Otherwise, if `$withReference` is `true` and a commit reference
     * is available, `-<reference>` is added.
     *
     * @api
     *
     * @param bool $pretty If `true`, return the original version number, e.g.
     * `v1.2.3` instead of `1.2.3.0`.
     * @return string|null `null` if `$package` is not installed.
     */
    public static function packageVersion(
        string $package,
        bool $pretty = true,
        bool $withReference = false
    ): ?string {
        if (!InstalledVersions::isInstalled($package)) {
            return null;
        }
        return self::formatVersion(
            $pretty
                ? InstalledVersions::getPrettyVersion($package)
                : InstalledVersions::getVersion($package),
            $withReference,
            fn() => self::packageReference($package)
        );
    }

    /**
     * Get the canonical path of an installed package
     *
     * @api
     *
     * @return string|null `null` if `$package` is not installed.
     */
    public static function packagePath(string $package): ?string
    {
        if (!InstalledVersions::isInstalled($package)) {
            return null;
        }

        return InstalledVersions::getInstallPath($package);
    }

    /**
     * Use ClassLoader to find the file where a class is defined
     *
     * @param class-string $class
     * @return string|null `null` if `$class` doesn't exist.
     *
     * @see Package::namespacePath()
     */
    public static function classPath(string $class): ?string
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
     * Use ClassLoader to resolve a namespace to a directory, which need not
     * exist
     *
     * @return string|null `null` if `$namespace` doesn't match a PSR-4 prefix
     * registered with Composer. Preference is given to the longest prefix where
     * a directory for the namespace already exists. If there is no match where
     * the namespace resolves to an existing directory, preference is given to
     * the longest prefix.
     */
    public static function namespacePath(string $namespace): ?string
    {
        $namespace = trim($namespace, '\\');

        $prefixes = [];
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $prefixes = array_merge_recursive($loader->getPrefixesPsr4(), $prefixes);
        }

        // Sort prefixes from longest to shortest
        uksort(
            $prefixes,
            fn(string $p1, string $p2): int =>
                strlen($p2) <=> strlen($p1)
        );

        foreach ($prefixes as $prefix => $dirs) {
            if (strcasecmp(substr($namespace . '\\', 0, strlen($prefix)), $prefix)) {
                continue;
            }
            foreach ((array) $dirs as $dir) {
                if (!is_dir($dir)) {
                    continue;
                }
                $dir = File::realpath($dir);
                $subdir = strtr(substr($namespace, strlen($prefix)), '\\', '/');
                $path = Arr::implode('/', [$dir, $subdir]);
                if (is_dir($path)) {
                    return $path;
                }
                $fallback = $fallback ?? $path;
            }
        }

        return $fallback ?? null;
    }

    /**
     * @return string[]|string|bool|null
     */
    private static function getRootPackageValue(string $key)
    {
        $values = self::filterData(
            InstalledVersions::getRootPackage(),
            'getRootPackage',
            InstalledVersions::class,
        );

        if (!array_key_exists($key, $values)) {
            // @codeCoverageIgnoreStart
            throw new UnexpectedValueException(
                sprintf('Value not found in root package: %s', $key)
            );
            // @codeCoverageIgnoreEnd
        }

        return $values[$key];
    }

    /**
     * @template TData
     *
     * @param TData $data
     * @param class-string<InstalledVersions|ClassLoader> $class
     * @param mixed ...$args
     * @return TData
     */
    private static function filterData(
        $data,
        string $method,
        string $class,
        ...$args
    ) {
        $event = new PackageDataReceivedEvent($data, $method, $class, ...$args);

        return Event::getInstance()->dispatch($event)->getData();
    }

    private static function formatVersion(
        string $version,
        bool $withRef,
        callable $refCallback
    ): string {
        if (Pcre::match('/(?:^dev-|-dev$)/', $version)) {
            $ref = $refCallback();
            if ($ref !== null) {
                return $version . "@$ref";
            }
            return $version;
        }
        if ($withRef) {
            $ref = $refCallback();
            if ($ref !== null) {
                return $version . "-$ref";
            }
        }
        return $version;
    }

    private static function formatReference(
        ?string $ref,
        bool $short
    ): ?string {
        if ($ref === null || !$short) {
            return $ref;
        }
        return substr($ref, 0, self::SHORT_REFERENCE_LENGTH);
    }
}
