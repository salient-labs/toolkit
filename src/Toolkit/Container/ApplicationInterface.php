<?php declare(strict_types=1);

namespace Salient\Container;

use Lkrms\Sync\Contract\ISyncClassResolver;
use Lkrms\Sync\Support\SyncStore;
use Salient\Cache\CacheStore;
use Salient\Console\Catalog\ConsoleLevel as Level;
use Salient\Core\Facade\Profile;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\Package;

/**
 * A service container for applications
 *
 * @api
 */
interface ApplicationInterface extends ContainerInterface
{
    /**
     * Get the name of the application
     */
    public function getAppName(): string;

    /**
     * Check if the application is running in a production environment
     *
     * This method should return `true` if:
     *
     * - {@see Env::environment()} returns `"production"`
     * - a Phar archive is currently executing, or
     * - the application was installed with `composer --no-dev`
     *
     * @see Package::hasDevPackages()
     */
    public function isProduction(): bool;

    /**
     * Get the application's root directory
     */
    public function getBasePath(): string;

    /**
     * Get a writable cache directory for the application
     *
     * The application's cache directory is appropriate for data that should
     * persist between runs, but isn't important or portable enough for the data
     * directory.
     */
    public function getCachePath(): string;

    /**
     * Get a writable directory for configuration files created by the
     * application
     */
    public function getConfigPath(): string;

    /**
     * Get a writable data directory for the application
     *
     * The application's data directory is appropriate for data that should
     * persist indefinitely.
     */
    public function getDataPath(): string;

    /**
     * Get a writable directory for the application's log files
     */
    public function getLogPath(): string;

    /**
     * Get a writable directory for the application's ephemeral data
     */
    public function getTempPath(): string;

    /**
     * Log console output to the application's log directory
     *
     * Messages with levels between {@see Level::EMERGENCY} and
     * {@see Level::INFO} are written to `<name>.log`.
     *
     * If `$debug` is `true`, or `$debug` is `null` and {@see Env::debug()}
     * returns `true`, messages with levels between {@see Level::EMERGENCY} and
     * {@see Level::DEBUG} are simultaneously written to `<name>.debug.log`.
     *
     * @param string|null $name If `null`, the name returned by
     * {@see ApplicationInterface::getAppName()} is used.
     * @return $this
     */
    public function logOutput(?string $name = null, ?bool $debug = null);

    /**
     * Start a cache store in the application's cache directory
     *
     * @see CacheStore
     *
     * @return $this
     */
    public function startCache();

    /**
     * Start a cache store in the application's cache directory if a backing
     * database was created on a previous run
     *
     * @see CacheStore
     *
     * @return $this
     */
    public function resumeCache();

    /**
     * Stop a previously started cache store
     *
     * @return $this
     */
    public function stopCache();

    /**
     * Start an entity store in the application's data directory
     *
     * If an entity store is started but not stopped by calling
     * {@see ApplicationInterface::stopSync()} or {@see SyncStore::close()}, a
     * failed run may be recorded.
     *
     * @see SyncStore
     *
     * @param mixed[] $arguments
     * @return $this
     */
    public function startSync(?string $command = null, ?array $arguments = null);

    /**
     * Stop a previously started entity store
     *
     * If an entity store is started but not stopped, a failed run may be
     * recorded.
     *
     * @return $this
     */
    public function stopSync();

    /**
     * Register a sync entity namespace with a previously started entity store
     *
     * A prefix can only be associated with one namespace per application and
     * cannot be changed without resetting the entity store's backing database.
     *
     * If a prefix has already been registered, its previous URI and PHP
     * namespace are updated if they differ. This is by design and is intended
     * to facilitate refactoring.
     *
     * @see SyncStore::namespace()
     *
     * @param string $prefix A short alternative to `$uri`. Case-insensitive.
     * Must be unique to the application. Must be a scheme name compliant with
     * Section 3.1 of \[RFC3986], i.e. a match for the regular expression
     * `^[a-zA-Z][a-zA-Z0-9+.-]*$`.
     * @param string $uri A globally unique namespace URI.
     * @param string $namespace A fully-qualified PHP namespace.
     * @param class-string<ISyncClassResolver>|null $resolver
     * @return $this
     */
    public function syncNamespace(
        string $prefix,
        string $uri,
        string $namespace,
        ?string $resolver = null
    );

    /**
     * Get the application's working directory
     *
     * The application's working directory is either:
     *
     * - the directory in which it was started, or
     * - the directory most recently set by calling
     *   {@see ApplicationInterface::setWorkingDirectory()}
     */
    public function getWorkingDirectory(): string;

    /**
     * Change to the application's working directory
     *
     * @return $this
     */
    public function restoreWorkingDirectory();

    /**
     * Set the application's working directory
     *
     * @param string|null $directory If `null`, the current working directory is
     * used.
     * @return $this
     */
    public function setWorkingDirectory(?string $directory = null);

    /**
     * Print a summary of the application's runtime performance metrics and
     * system resource usage when it terminates
     *
     * Use {@see Profile::startTimer()}, {@see Profile::stopTimer()} and
     * {@see Profile::count()} to collect performance metrics.
     *
     * @param Level::* $level
     * @param string[]|string|null $groups If `null` or `["*"]`, all metrics are
     * reported, otherwise only metrics in the given groups are reported.
     * @return $this
     *
     * @see ApplicationInterface::reportResourceUsage()
     * @see ApplicationInterface::reportTimers()
     */
    public function registerShutdownReport(
        int $level = Level::INFO,
        $groups = null,
        bool $resourceUsage = true
    );

    /**
     * Print a summary of the application's system resource usage
     *
     * @param Level::* $level
     * @return $this
     */
    public function reportResourceUsage(int $level = Level::INFO);

    /**
     * Print a summary of the application's runtime performance metrics
     *
     * @param Level::* $level
     * @param string[]|string|null $groups If `null` or `["*"]`, all metrics are
     * reported, otherwise only metrics in the given groups are reported.
     * @return $this
     *
     * @see Profile::startTimer()
     * @see Profile::stopTimer()
     * @see Profile::count()
     */
    public function reportMetrics(
        int $level = Level::INFO,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
    );
}
