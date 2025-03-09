<?php declare(strict_types=1);

namespace Salient\Contract\Container;

use Salient\Contract\Console\ConsoleInterface as Console;
use Salient\Contract\Curler\CurlerInterface;
use Salient\Contract\Sync\SyncNamespaceHelperInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Closure;
use LogicException;

/**
 * @api
 */
interface ApplicationInterface extends ContainerInterface
{
    /**
     * Get the name of the application
     */
    public function getName(): string;

    /**
     * Get the application's base path
     */
    public function getBasePath(): string;

    /**
     * Get a cache directory for the application
     */
    public function getCachePath(): string;

    /**
     * Get a directory for configuration files created by the application
     */
    public function getConfigPath(): string;

    /**
     * Get a data directory for the application
     */
    public function getDataPath(): string;

    /**
     * Get a directory for the application's log files
     */
    public function getLogPath(): string;

    /**
     * Get a directory for the application's temporary files
     */
    public function getTempPath(): string;

    /**
     * Check if the application is running in a production environment
     */
    public function isRunningInProduction(): bool;

    /**
     * Log console output to the application's log directory
     *
     * Messages with levels between `LEVEL_EMERGENCY` and `LEVEL_INFO` are
     * written to `<name>.log`.
     *
     * If `$debug` is `true`, or `$debug` is `null` and debug mode is enabled in
     * the environment, messages with levels between `LEVEL_EMERGENCY` and
     * `LEVEL_DEBUG` are also written to `<name>.debug.log`.
     *
     * @param string|null $name If `null`, the name of the application is used.
     * @return $this
     */
    public function logOutput(?string $name = null, ?bool $debug = null);

    /**
     * Export the application's HTTP requests to an HTTP Archive (HAR) file in
     * its log directory
     *
     * If any HTTP requests are made via {@see CurlerInterface} implementations,
     * they are recorded in `<name>-<timestamp>-<uuid>.har`.
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
     * Get the name of the application's HTTP Archive (HAR) file if it exists
     *
     * Returns `null` if {@see exportHar()} has been called but no HTTP requests
     * have been made.
     *
     * @throws LogicException if HTTP requests are not being recorded.
     */
    public function getHarFilename(): ?string;

    /**
     * Start a cache for the application and make it the global cache
     *
     * If the cache is filesystem-backed, it is started in the application's
     * cache directory.
     *
     * @return $this
     */
    public function startCache();

    /**
     * Stop the application's cache if started
     *
     * @return $this
     */
    public function stopCache();

    /**
     * Start a sync entity store for the application and make it the global sync
     * entity store
     *
     * If the entity store is filesystem-backed, it is started in the
     * application's data directory.
     *
     * @param string[]|null $arguments
     * @return $this
     */
    public function startSync(?string $command = null, ?array $arguments = null);

    /**
     * Stop the application's sync entity store if started
     *
     * @return $this
     */
    public function stopSync();

    /**
     * Register a namespace for sync entities and their provider interfaces with
     * the application's sync entity store, starting it if necessary
     *
     * @see SyncStoreInterface::registerNamespace()
     *
     * @return $this
     */
    public function sync(
        string $prefix,
        string $uri,
        string $namespace,
        ?SyncNamespaceHelperInterface $helper = null
    );

    /**
     * Get the directory in which the application was started
     */
    public function getInitialWorkingDirectory(): string;

    /**
     * Set the directory in which the application was started
     *
     * @return $this
     */
    public function setInitialWorkingDirectory(string $directory);

    /**
     * Change to the directory in which the application was started
     *
     * @return $this
     */
    public function restoreWorkingDirectory();

    /**
     * Write a summary of the application's system resource usage to the console
     *
     * @param Console::LEVEL_* $level
     * @return $this
     */
    public function reportResourceUsage(int $level = Console::LEVEL_INFO);

    /**
     * Write a summary of the application's runtime performance metrics to the
     * console
     *
     * @param Console::LEVEL_* $level
     * @param string[]|string|null $groups If `null` or `["*"]`, all metrics are
     * reported, otherwise only metrics in the given groups are reported.
     * @return $this
     */
    public function reportMetrics(
        int $level = Console::LEVEL_INFO,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
    );

    /**
     * Write a summary of the application's runtime performance metrics and
     * system resource usage to the console when it terminates
     *
     * @param Console::LEVEL_* $level
     * @param string[]|string|null $groups If `null` or `["*"]`, all metrics are
     * reported, otherwise only metrics in the given groups are reported.
     * @return $this
     */
    public function registerShutdownReport(
        int $level = Console::LEVEL_INFO,
        bool $includeResourceUsage = true,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
    );
}
