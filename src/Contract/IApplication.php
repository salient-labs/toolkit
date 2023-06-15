<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevels;
use Lkrms\Sync\Contract\ISyncClassResolver;

/**
 * A service container for applications
 *
 */
interface IApplication extends IContainer, ReturnsEnvironment
{
    public function __construct(?string $basePath = null);

    /**
     * Get the basename of the file used to run the script
     *
     */
    public function getProgramName(): string;

    /**
     * Get the basename of the file used to run the script, removing known PHP
     * file extensions and recognised version numbers
     *
     */
    public function getAppName(): string;

    /**
     * True if the application is in production, false if it's running from
     * source
     *
     * "In production" means one of the following is true:
     * - a Phar archive is currently executing, or
     * - the application was installed with `composer --no-dev`
     *
     * @see \Lkrms\Utility\Composer::hasDevDependencies()
     */
    public function inProduction(): bool;

    /**
     * Get the application's root directory
     *
     */
    public function getBasePath(): string;

    /**
     * Get a writable cache directory for the application
     *
     * The application's cache directory is appropriate for data that should
     * persist between runs, but isn't important or portable enough for the data
     * directory.
     *
     */
    public function getCachePath(): string;

    /**
     * Get a writable directory for the application's configuration files
     *
     */
    public function getConfigPath(): string;

    /**
     * Get a writable data directory for the application
     *
     * The application's data directory is appropriate for data that should
     * persist indefinitely.
     *
     */
    public function getDataPath(): string;

    /**
     * Get a writable directory for the application's log files
     *
     */
    public function getLogPath(): string;

    /**
     * Get a writable directory for the application's ephemeral data
     *
     */
    public function getTempPath(): string;

    /**
     * Log console messages to a file in the application's log directory
     *
     * Registers a {@see StreamTarget} to log subsequent
     * {@see ConsoleLevels::ALL} messages to `<name>.log`.
     *
     * {@see ConsoleLevels::ALL_DEBUG} messages are simultaneously logged to
     * `<name>.debug.log` in the same location if:
     * - `$debug` is `true`, or
     * - `$debug` is `null` and {@see \Lkrms\Utility\Environment::debug()}
     *   returns `true`
     *
     * @param string|null $name Defaults to the name returned by
     * {@see IApplication::getAppName()}.
     * @return $this
     */
    public function logConsoleMessages(?bool $debug = null, ?string $name = null);

    /**
     * Load the application's CacheStore, creating a backing database if needed
     *
     * The backing database is created in {@see IApplication::getCachePath()}.
     *
     * @return $this
     * @see \Lkrms\Store\CacheStore
     */
    public function loadCache();

    /**
     * Load the application's CacheStore if a backing database already exists
     *
     * Caching is only enabled if a backing database created by
     * {@see IApplication::loadCache()} is found in
     * {@see IApplication::getCachePath()}.
     *
     * @return $this
     * @see \Lkrms\Store\CacheStore
     */
    public function loadCacheIfExists();

    /**
     * Load the application's SyncStore, creating a backing database if needed
     *
     * The backing database is created in {@see IApplication::getDataPath()}.
     *
     * Call {@see IApplication::unloadSync()} before the application terminates,
     * otherwise a failed run may be recorded.
     *
     * @param mixed[] $arguments
     * @return $this
     * @see \Lkrms\Sync\Support\SyncStore
     */
    public function loadSync(?string $command = null, ?array $arguments = null);

    /**
     * Close the application's SyncStore
     *
     * If this method is not called after calling
     * {@see IApplication::loadSync()}, a failed run may be recorded.
     *
     * @return $this
     */
    public function unloadSync(bool $silent = false);

    /**
     * Register a sync entity namespace with the application's SyncStore
     *
     * A prefix can only be associated with one namespace per application and
     * cannot be changed unless the {@see \Lkrms\Sync\Support\SyncStore}'s
     * backing database has been reset.
     *
     * If `$prefix` has already been registered, its previous URI and PHP
     * namespace are updated if they differ.
     *
     * @param string $prefix A short alternative to `$uri`. Case-insensitive.
     * Must be unique within the scope of the application. Must be a scheme name
     * that complies with Section 3.1 of [RFC3986], i.e. a match for the regular
     * expression `^[a-zA-Z][a-zA-Z0-9+.-]*$`.
     * @param string $uri A globally unique namespace URI.
     * @param string $namespace A fully-qualified PHP namespace.
     * @param class-string<ISyncClassResolver>|null $resolver
     * @return $this
     * @see \Lkrms\Sync\Support\SyncStore::namespace()
     */
    public function syncNamespace(string $prefix, string $uri, string $namespace, ?string $resolver = null);

    /**
     * Print a summary of the script's timers and system resource usage when the
     * application terminates
     *
     * Use {@see \Lkrms\Utility\System::startTimer()} and
     * {@see \Lkrms\Utility\System::stopTimer()} to collect timing information.
     *
     * @param string[]|null $timers If `null` or empty, timers aren't reported. If
     * `['*']` (the default), all timers are reported. Otherwise, only timers of
     * the specified types are reported.
     * @return $this
     * @see IApplication::writeResourceUsage()
     * @see IApplication::writeTimers()
     */
    public function registerShutdownReport(
        int $level = Level::INFO,
        ?array $timers = ['*'],
        bool $resourceUsage = true
    );

    /**
     * Print a summary of the script's system resource usage
     *
     * Example output:
     *
     * ```
     * CPU time: 0.011s elapsed, 0.035s user, 0.016s system; memory: 3.817MiB peak
     *
     * ```
     *
     * @return $this
     */
    public function writeResourceUsage(int $level = Level::INFO);

    /**
     * Print a summary of the script's timers
     *
     * Example output:
     *
     * ```
     * Timing: 6.281ms recorded by 1 timer with type 'file':
     *   6.281ms {1} lk-util
     *
     * Timing: 1.863ms recorded by 26 timers with type 'rule':
     *   0.879ms {2} PreserveOneLineStatements
     *   0.153ms {2} AddStandardWhitespace
     *   0.145ms {2} AddHangingIndentation
     *   ...
     *   0.042ms {2} AlignArguments
     *               (and 16 more)
     *
     * ```
     *
     * @return $this
     * @see \Lkrms\Utility\System::startTimer()
     * @see \Lkrms\Utility\System::stopTimer()
     * @see \Lkrms\Utility\System::getTimers()
     */
    public function writeTimers(
        int $level = Level::INFO,
        bool $includeRunning = true,
        ?string $type = null,
        ?int $limit = 10
    );
}
