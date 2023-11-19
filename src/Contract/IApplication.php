<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Store\CacheStore;
use Lkrms\Support\Timekeeper;
use Lkrms\Sync\Contract\ISyncClassResolver;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Utility\Catalog\EnvFlag;
use Lkrms\Utility\Env;
use Lkrms\Utility\Package;

/**
 * A service container for applications
 */
interface IApplication extends IContainer, HasEnvironment
{
    /**
     * @inheritDoc
     *
     * @param string|null $basePath If `null`, the value of environment variable
     * `app_base_path` is used if present, otherwise the path of the root
     * package is used.
     * @param string|null $appName If `null`, the value returned by
     * {@see IApplication::getProgramName()} is used after removing common PHP
     * file extensions and recognised version numbers.
     * @param int-mask-of<EnvFlag::*> $envFlags
     */
    public function __construct(
        ?string $basePath = null,
        ?string $appName = null,
        int $envFlags = EnvFlag::ALL
    );

    /**
     * Get the basename of the file used to run the script
     */
    public function getProgramName(): string;

    /**
     * Get the name of the application
     */
    public function getAppName(): string;

    /**
     * Check if the application is running in a production environment
     *
     * Returns `true` if:
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
     * Get a writable directory for the application's configuration files
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
     * {@see IApplication::getAppName()} is used.
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
     * {@see IApplication::stopSync()} or {@see SyncStore::close()}, a failed
     * run may be recorded.
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
     * Must be unique to the application. Must be a scheme name that complies
     * with Section 3.1 of \[RFC3986], i.e. a match for the regular expression
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
     * Print a summary of the application's timers and system resource usage
     * when it terminates
     *
     * Use {@see Timekeeper::startTimer()} and {@see Timekeeper::stopTimer()} to
     * collect timing information.
     *
     * @param Level::* $level
     * @param string[]|string|null $timerTypes If `null` or `["*"]`, all timers
     * are reported, otherwise only timers of the given types are reported.
     * @return $this
     *
     * @see IApplication::reportResourceUsage()
     * @see IApplication::reportTimers()
     */
    public function registerShutdownReport(
        int $level = Level::INFO,
        $timerTypes = null,
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
     * Print a summary of the application's timers
     *
     * @param Level::* $level
     * @param string[]|string|null $types If `null` or `["*"]`, all timers are
     * reported, otherwise only timers of the given types are reported.
     * @return $this
     *
     * @see Timekeeper::startTimer()
     * @see Timekeeper::stopTimer()
     * @see Timekeeper::getTimers()
     */
    public function reportTimers(
        int $level = Level::INFO,
        bool $includeRunning = true,
        $types = null,
        ?int $limit = 10
    );
}
