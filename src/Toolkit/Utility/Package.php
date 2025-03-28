<?php declare(strict_types=1);

namespace Salient\Utility;

use Composer\Autoload\ClassLoader as Loader;
use Composer\InstalledVersions as Installed;
use Salient\Core\Facade\Event;
use Salient\Utility\Event\PackageDataReceivedEvent;
use Salient\Utility\Exception\ShouldNotHappenException;
use Closure;

/**
 * Get information from Composer's runtime API
 *
 * @api
 */
final class Package extends AbstractUtility
{
    private const SHORT_REFERENCE_LENGTH = 8;

    /**
     * Check if require-dev packages are installed
     */
    public static function hasDevPackages(): bool
    {
        /** @var bool */
        return self::getRootPackageValue('dev');
    }

    /**
     * Get the name of the root package
     */
    public static function name(): string
    {
        /** @var string */
        return self::getRootPackageValue('name');
    }

    /**
     * Get the commit reference of the root package, if known
     */
    public static function ref(bool $short = true): ?string
    {
        /** @var string|null */
        $ref = self::getRootPackageValue('reference');
        return self::formatRef($ref, $short);
    }

    /**
     * Get the version of the root package
     *
     * If Composer returns a version like `dev-*` or `v1.x-dev` and `$withRef`
     * is not `false`, `@<reference>` is added. Otherwise, if `$withRef` is
     * `true` and a commit reference is available, `-<reference>` is added.
     *
     * @param bool $pretty If `true`, return the original version number, e.g.
     * `v1.2.3` instead of `1.2.3.0`.
     */
    public static function version(
        bool $pretty = true,
        ?bool $withRef = null
    ): string {
        /** @var string */
        $version = self::getRootPackageValue($pretty ? 'pretty_version' : 'version');
        return self::formatVersion($version, $withRef, fn() => self::ref());
    }

    /**
     * Get the canonical path of the root package
     */
    public static function path(): string
    {
        /** @var string */
        $path = self::getRootPackageValue('install_path');
        return File::realpath($path);
    }

    /**
     * Get the commit reference of an installed package, if known
     */
    public static function getPackageRef(string $package, bool $short = true): ?string
    {
        if (!self::isInstalled($package)) {
            return null;
        }

        $ref = self::filterData(
            Installed::getReference($package),
            Installed::class,
            'getReference',
            $package,
        );

        return self::formatRef($ref, $short);
    }

    /**
     * Get the version of an installed package, or null if it is not installed
     *
     * If Composer returns a version like `dev-*` or `v1.x-dev` and `$withRef`
     * is not `false`, `@<reference>` is added. Otherwise, if `$withRef` is
     * `true` and a commit reference is available, `-<reference>` is added.
     *
     * @param bool $pretty If `true`, return the original version number, e.g.
     * `v1.2.3` instead of `1.2.3.0`.
     */
    public static function getPackageVersion(
        string $package,
        bool $pretty = true,
        ?bool $withRef = null
    ): ?string {
        if (!self::isInstalled($package)) {
            return null;
        }

        return self::formatVersion(
            (string) self::getVersion($package, $pretty),
            $withRef,
            fn() => self::getPackageRef($package),
        );
    }

    /**
     * Get the canonical path of an installed package, or null if it is not
     * installed
     */
    public static function getPackagePath(string $package): ?string
    {
        if (!self::isInstalled($package)) {
            return null;
        }

        $path = self::filterData(
            Installed::getInstallPath($package),
            Installed::class,
            'getInstallPath',
            $package,
        );

        return $path !== null
            ? File::realpath($path)
            : null;
    }

    /**
     * Use ClassLoader to get the file where a class is defined, or null if it
     * doesn't exist
     *
     * @param class-string $class
     */
    public static function getClassPath(string $class): ?string
    {
        $class = ltrim($class, '\\');
        foreach (self::getRegisteredLoaders() as $loader) {
            $file = self::filterData(
                $loader->findFile($class),
                Loader::class,
                'findFile',
                $class,
            );

            if ($file !== false) {
                /** @var string */
                return $file;
            }
        }

        return null;
    }

    /**
     * Use ClassLoader to get a directory for a namespace, or null if the
     * namespace doesn't match a registered PSR-4 prefix
     *
     * Preference is given to the longest prefix where a directory for the
     * namespace already exists. If no such prefix exists, preference is given
     * to the longest prefix.
     */
    public static function getNamespacePath(string $namespace): ?string
    {
        $namespace = trim($namespace, '\\');

        $prefixes = [];
        foreach (self::getRegisteredLoaders() as $loader) {
            $loaderPrefixes = self::filterData(
                $loader->getPrefixesPsr4(),
                Loader::class,
                'getPrefixesPsr4',
            );
            $prefixes = array_merge_recursive($loaderPrefixes, $prefixes);
        }

        // Sort prefixes from longest to shortest
        uksort(
            $prefixes,
            fn($p1, $p2) => strlen($p2) <=> strlen($p1)
        );

        foreach ($prefixes as $prefix => $dirs) {
            if (strcasecmp(substr($namespace . '\\', 0, strlen($prefix)), $prefix)) {
                continue;
            }

            foreach ((array) $dirs as $dir) {
                if (is_dir($dir)) {
                    $dir = File::realpath($dir);
                    $subdir = strtr(substr($namespace, strlen($prefix)), '\\', '/');
                    $path = Arr::implode('/', [$dir, $subdir], '');
                    if (is_dir($path)) {
                        return $path;
                    }
                    $fallback ??= $path;
                }
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
            Installed::getRootPackage(),
            Installed::class,
            'getRootPackage',
        );

        if (!array_key_exists($key, $values)) {
            // @codeCoverageIgnoreStart
            throw new ShouldNotHappenException(sprintf(
                'Value not found in root package: %s',
                $key,
            ));
            // @codeCoverageIgnoreEnd
        }

        return $values[$key];
    }

    /**
     * Check if a package is installed
     */
    public static function isInstalled(string $package): bool
    {
        return self::filterData(
            Installed::isInstalled($package),
            Installed::class,
            'isInstalled',
            $package,
        );
    }

    private static function getVersion(string $package, bool $pretty): ?string
    {
        return self::filterData(
            $pretty
                ? Installed::getPrettyVersion($package)
                : Installed::getVersion($package),
            Installed::class,
            $pretty ? 'getPrettyVersion' : 'getVersion',
            $package,
        );
    }

    /**
     * @return array<string,Loader>
     */
    private static function getRegisteredLoaders(): array
    {
        return self::filterData(
            Loader::getRegisteredLoaders(),
            Loader::class,
            'getRegisteredLoaders',
        );
    }

    /**
     * @template TData
     *
     * @param TData $data
     * @param class-string<Installed|Loader> $class
     * @param mixed ...$args
     * @return TData
     */
    private static function filterData($data, string $class, string $method, ...$args)
    {
        if (class_exists(Event::class) && Event::isLoaded()) {
            $event = new PackageDataReceivedEvent($data, $class, $method, ...$args);
            $data = Event::getInstance()->dispatch($event)->getData();
        }
        return $data;
    }

    /**
     * @param Closure(): ?string $refCallback
     */
    private static function formatVersion(
        string $version,
        ?bool $withRef,
        Closure $refCallback
    ): string {
        if ($withRef !== false && Regex::match('/(?:^dev-|-dev$)/D', $version)) {
            $ref = $refCallback();
            if ($ref !== null && !Str::startsWith($version, ['dev-' . $ref, $ref])) {
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

    private static function formatRef(?string $ref, bool $short): ?string
    {
        if ($ref === null || !$short) {
            return $ref;
        }
        return substr($ref, 0, self::SHORT_REFERENCE_LENGTH);
    }
}
