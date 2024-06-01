<?php declare(strict_types=1);

namespace Salient\Contract\Container;

use Salient\Cache\CacheStore;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Sync\SyncClassResolverInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Facade\Profile;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\Package;
use Salient\Sync\SyncStore;
use LogicException;

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
     * Start a SQLite-backed cache store in the application's cache directory
     * and make it the Cache facade's underlying instance
     *
     * @see CacheStore
     *
     * @return $this
     */
    public function startCache();

    /**
     * Start a SQLite-backed cache store if a database file created by
     * startCache() on a previous run is found
     *
     * @return $this
     */
    public function resumeCache();

    /**
     * Stop a cache store started by startCache() or resumeCache()
     *
     * @return $this
     */
    public function stopCache();

    /**
     * Start a SQLite-backed entity store in the application's data directory
     * and make it the Sync facade's underlying instance
     *
     * @see SyncStore
     *
     * @param mixed[] $arguments
     * @return $this
     */
    public function startSync(?string $command = null, ?array $arguments = null);

    /**
     * Stop an entity store started by startSync()
     *
     * @return $this
     */
    public function stopSync();

    /**
     * Register a namespace for sync entities and their provider interfaces with
     * the global sync entity store if it is loaded
     *
     * @see SyncStoreInterface::registerNamespace()
     *
     * @return $this
     * @throws LogicException if the global sync entity store is not loaded.
     */
    public function syncNamespace(
        string $prefix,
        string $uri,
        string $namespace,
        ?SyncClassResolverInterface $resolver = null
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
