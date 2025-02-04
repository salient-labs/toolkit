<?php declare(strict_types=1);

namespace Salient\Contract\Container;

use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Sync\SyncNamespaceHelperInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Closure;
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
     * Returns `true` if:
     *
     * - the name of the current environment is `"production"`
     * - the application is running from a Phar archive, or
     * - the application was installed with `composer --no-dev`
     */
    public function isProduction(): bool;

    /**
     * Get the application's base path
     */
    public function getBasePath(): string;

    /**
     * Get a writable cache directory for the application
     *
     * Appropriate for replaceable data that should persist between runs to
     * improve performance.
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
     * Appropriate for critical data that should persist indefinitely.
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
     * If `$debug` is `true`, or `$debug` is `null` and debug mode is enabled in
     * the environment, messages with levels between {@see Level::EMERGENCY} and
     * {@see Level::DEBUG} are simultaneously written to `<name>.debug.log`.
     *
     * @param string|null $name If `null`, the name of the application is used.
     * @return $this
     */
    public function logOutput(?string $name = null, ?bool $debug = null);

    /**
     * Export the application's HTTP requests to an HTTP Archive (HAR) file in
     * its log directory
     *
     * If any requests are made via {@see CurlerInterface} objects,
     * `<name>-<timestamp>-<uuid>.har` is created to record them.
     *
     * @param string|null $name If `null`, the name of the application is used.
     * @param (Closure(): string)|string|null $uuid
     * @return $this
     * @throws LogicException if HTTP requests are already being recorded.
     */
    public function exportHar(
        ?string $name = null,
        ?string $creatorName = null,
        ?string $creatorVersion = null,
        $uuid = null
    );

    /**
     * Get the name of the HTTP Archive (HAR) file created via exportHar() if it
     * has been created
     *
     * @throws LogicException if HTTP requests are not being recorded.
     */
    public function getHarFilename(): ?string;

    /**
     * Start a cache store and make it the global cache
     *
     * If the cache store is filesystem-backed, the application's cache
     * directory is used.
     *
     * @return $this
     */
    public function startCache();

    /**
     * Start a cache store and make it the global cache if a previously started
     * cache store exists, otherwise do nothing
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
     * Start an entity store and make it the global sync entity store
     *
     * If the entity store is filesystem-backed, the application's data
     * directory is used.
     *
     * @param string[]|null $arguments
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
     * the global sync entity store
     *
     * @see SyncStoreInterface::registerNamespace()
     *
     * @return $this
     * @throws LogicException if the global sync entity store is not loaded.
     */
    public function registerSyncNamespace(
        string $prefix,
        string $uri,
        string $namespace,
        ?SyncNamespaceHelperInterface $helper = null
    );

    /**
     * Get the application's working directory
     *
     * The application's working directory is either the directory it was
     * started in, or the directory most recently set via
     * {@see ApplicationInterface::setWorkingDirectory()}.
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
     * @param Level::* $level
     * @param string[]|string|null $groups If `null` or `["*"]`, all metrics are
     * reported, otherwise only metrics in the given groups are reported.
     * @return $this
     */
    public function registerShutdownReport(
        int $level = Level::INFO,
        bool $includeResourceUsage = true,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
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
     */
    public function reportMetrics(
        int $level = Level::INFO,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
    );
}
