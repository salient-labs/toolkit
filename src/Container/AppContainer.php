<?php declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Concern\TReadable;
use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Console\ConsoleLevels;
use Lkrms\Console\Target\StreamTarget;
use Lkrms\Container\Container;
use Lkrms\Contract\IReadable;
use Lkrms\Err\Err;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Lkrms\Facade\Format;
use Lkrms\Facade\Sync;
use Lkrms\Facade\Sys;
use Lkrms\Facade\Test;
use Phar;
use RuntimeException;
use UnexpectedValueException;

/**
 * A service container for applications
 *
 * Typically accessed via the {@see \Lkrms\Facade\App} facade.
 *
 * @property-read string $BasePath
 * @property-read string $CachePath
 * @property-read string $ConfigPath
 * @property-read string $DataPath
 * @property-read string $LogPath
 * @property-read string $TempPath
 */
class AppContainer extends Container implements IReadable
{
    use TReadable;

    /**
     * Typically ~/.config/<app>
     */
    private const DIR_CONFIG = 'CONFIG';

    /**
     * Typically ~/.local/share/<app>
     */
    private const DIR_DATA = 'DATA';

    /**
     * Typically ~/.cache/<app>
     */
    private const DIR_STATE = 'STATE';

    /**
     * @var string
     */
    protected $BasePath;

    /**
     * @internal
     * @var string
     */
    protected $_CachePath;

    /**
     * @internal
     * @var string
     */
    protected $_ConfigPath;

    /**
     * @internal
     * @var string
     */
    protected $_DataPath;

    /**
     * @internal
     * @var string
     */
    protected $_LogPath;

    /**
     * @internal
     * @var string
     */
    protected $_TempPath;

    /**
     * @var int|float
     */
    private $StartTime;

    /**
     * @var StreamTarget[]
     */
    private $LogTargets = [];

    /**
     * @var StreamTarget[]
     */
    private $DebugLogTargets = [];

    private function getPath(string $name, string $parent, ?string $child, string $sourceChild, string $windowsChild): string
    {
        $name = "app_{$name}_path";
        if ($path = Env::get($name, null)) {
            if (!Test::isAbsolutePath($path)) {
                $path = $this->BasePath . '/' . $path;
            }

            return $this->_getPath($path, $name);
        }

        // If running from source, return `$this->BasePath/$sourceChild` if it
        // resolves to a writable directory
        if (!$this->inProduction()) {
            $path = "{$this->BasePath}/$sourceChild";
            if (Test::firstExistingDirectoryIsWritable($path)) {
                return $this->_getPath($path, $name);
            }
        }

        $app = $this->getAppName();

        if (PHP_OS_FAMILY === 'Windows') {
            switch ($parent) {
                case self::DIR_CONFIG:
                case self::DIR_DATA:
                    $path = Env::get('APPDATA');
                    break;
                case self::DIR_STATE:
                default:
                    $path = Env::get('LOCALAPPDATA');
                    break;
            }

            return $this->_getPath(Convert::sparseToString(
                DIRECTORY_SEPARATOR,
                [$path, $app, $windowsChild]
            ), $name);
        }

        if (!($home = Env::home())) {
            throw new RuntimeException('Home directory not found');
        }

        switch ($parent) {
            case self::DIR_CONFIG:
                $path = Env::get('XDG_CONFIG_HOME', $home . '/.config');
                break;
            case self::DIR_DATA:
                $path = Env::get('XDG_DATA_HOME', $home . '/.local/share');
                break;
            case self::DIR_STATE:
            default:
                $path = Env::get('XDG_CACHE_HOME', $home . '/.cache');
                break;
        }

        return $this->_getPath(Convert::sparseToString(
            DIRECTORY_SEPARATOR,
            [$path, $app, $child]
        ), $name);
    }

    private function _getPath(string $path, string $name): string
    {
        if (!Test::isAbsolutePath($path)) {
            throw new UnexpectedValueException("Absolute path required: $name");
        }
        File::maybeCreateDirectory($path);

        return $path;
    }

    final protected function _getCachePath(): string
    {
        return $this->_CachePath
            ?: ($this->_CachePath = $this->getPath('cache', self::DIR_STATE, 'cache', 'var/cache', 'cache'));
    }

    final protected function _getConfigPath(): string
    {
        return $this->_ConfigPath
            ?: ($this->_ConfigPath = $this->getPath('config', self::DIR_CONFIG, null, 'config', 'config'));
    }

    final protected function _getDataPath(): string
    {
        return $this->_DataPath
            ?: ($this->_DataPath = $this->getPath('data', self::DIR_DATA, null, 'var/lib', 'data'));
    }

    final protected function _getLogPath(): string
    {
        return $this->_LogPath
            ?: ($this->_LogPath = $this->getPath('log', self::DIR_STATE, 'log', 'var/log', 'log'));
    }

    final protected function _getTempPath(): string
    {
        return $this->_TempPath
            ?: ($this->_TempPath = $this->getPath('temp', self::DIR_STATE, 'tmp', 'var/tmp', 'tmp'));
    }

    public static function getReadable(): array
    {
        return ['BasePath'];
    }

    public function __construct(string $basePath = null)
    {
        $this->StartTime = hrtime(true);

        parent::__construct();

        if (self::hasGlobalContainer() &&
                ($global = get_class(self::getGlobalContainer())) !== Container::class) {
            throw new RuntimeException('Global container already loaded: ' . $global);
        }

        self::setGlobalContainer($this);

        if (is_null($basePath)) {
            $basePath = Env::get('app_base_path', null) ?: Composer::getRootPackagePath();
        }
        if (!is_dir($basePath) ||
                ($basePath = File::realpath($basePath)) === false) {
            throw new RuntimeException('Invalid basePath: ' . $basePath);
        }
        $this->BasePath = $basePath;

        if (!Phar::running() &&
                is_file($env = $this->BasePath . '/.env')) {
            Env::loadFile($env);
        }
        Env::apply();

        Console::registerStdioTargets();

        register_shutdown_function(
            function () {
                if (Env::debug()) {
                    $this->writeTimers(true, null, Level::DEBUG);
                }
                $this->writeResourceUsage(Level::DEBUG);
            }
        );

        Err::load();
        if ($path = Composer::getPackagePath('adodb/adodb-php')) {
            Err::silencePaths($path);
        }
    }

    /**
     * Return true if the application is in production, false if it's running
     * from source
     *
     * "In production" means one of the following is true:
     * - a Phar archive is currently executing, or
     * - the application was installed with `composer --no-dev`
     *
     * @see Composer::hasDevDependencies()
     */
    public function inProduction(): bool
    {
        return Phar::running() ||
            !Composer::hasDevDependencies();
    }

    /**
     * Get the basename of the file used to run the script
     *
     */
    public function getProgramName(): string
    {
        return Sys::getProgramBasename();
    }

    /**
     * Get the basename of the file used to run the script, removing any PHP
     * file extensions
     *
     */
    final public function getAppName(): string
    {
        return Sys::getProgramBasename('.php', '.phar');
    }

    /**
     * Load the application's CacheStore, creating a backing database if needed
     *
     * The backing database is created in {@see AppContainer::$CachePath}.
     *
     * @return $this
     * @see \Lkrms\Store\CacheStore
     */
    final public function loadCache()
    {
        $cacheDb = $this->CachePath . '/cache.db';

        if (!Cache::isLoaded()) {
            Cache::load($cacheDb);
        } elseif (!Test::areSameFile($cacheDb, $file = Cache::getFilename() ?: '')) {
            throw new RuntimeException("Cache database already loaded: $file");
        }

        return $this;
    }

    /**
     * Load the application's CacheStore if a backing database already exists
     *
     * Caching is only enabled if a backing database created by
     * {@see AppContainer::loadCache()} is found in
     * {@see AppContainer::$CachePath}.
     *
     * @return $this
     * @see \Lkrms\Store\CacheStore
     */
    final public function loadCacheIfExists()
    {
        if (file_exists($this->CachePath . '/cache.db')) {
            $this->loadCache();
        }

        return $this;
    }

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
     * @param string|null $name Defaults to the name used to run the script.
     * @return $this
     */
    final public function logConsoleMessages(?bool $debug = null, ?string $name = null)
    {
        $name = $name ? basename($name, '.log') : $this->getAppName();
        if (!($this->LogTargets[$name] ?? null)) {
            $this->LogTargets[$name] = $target = StreamTarget::fromPath($this->LogPath . "/$name.log");
            Console::registerTarget($target, ConsoleLevels::ALL);
        }
        if (($debug || (is_null($debug) && Env::debug())) &&
                !($this->DebugLogTargets[$name] ?? null)) {
            $this->DebugLogTargets[$name] = $target = StreamTarget::fromPath($this->LogPath . "/$name.debug.log");
            Console::registerTarget($target, ConsoleLevels::ALL_DEBUG);
        }

        return $this;
    }

    /**
     * Load the application's SyncStore, creating a backing database if needed
     *
     * The backing database is created in {@see AppContainer::$DataPath}.
     *
     * Call {@see AppContainer::unloadSync()} before the application terminates,
     * otherwise a failed run will be recorded.
     *
     * @return $this
     * @see \Lkrms\Sync\Support\SyncStore
     */
    final public function loadSync(?string $command = null, ?array $arguments = null)
    {
        $syncDb = $this->DataPath . '/sync.db';

        if (!Sync::isLoaded()) {
            Sync::load($syncDb,
                       is_null($command) ? Sys::getProgramName($this->BasePath) : $command,
                       (is_null($arguments)
                           ? (PHP_SAPI == 'cli'
                               ? array_slice($_SERVER['argv'], 1)
                               : ['_GET' => $_GET, '_POST' => $_POST])
                           : $arguments));
        } elseif (!Test::areSameFile($syncDb, $file = Sync::getFilename() ?: '')) {
            throw new RuntimeException("Sync database already loaded: $file");
        }

        return $this;
    }

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
     * @return $this
     * @see \Lkrms\Sync\Support\SyncStore::namespace()
     */
    final public function syncNamespace(string $prefix, string $uri, string $namespace)
    {
        if (!Sync::isLoaded()) {
            throw new RuntimeException('Sync database not loaded');
        }
        Sync::namespace($prefix, $uri, $namespace);

        return $this;
    }

    /**
     * Close the application's SyncStore
     *
     * If this method is not called after calling
     * {@see AppContainer::loadSync()}, a failed run will be recorded.
     *
     * @return $this
     */
    final public function unloadSync(bool $silent = false)
    {
        if (!Sync::isLoaded()) {
            return $this;
        }

        Sync::close();

        if ($silent) {
            return $this;
        }

        if ($count = count($errors = Sync::getErrors())) {
            // Print an error message without incrementing `Console`'s error
            // counter
            Console::error(
                Convert::plural($count, 'sync error', null, true) . ' recorded:',
                "\n" . $errors,
                null,
                false
            );

            return $this;
        }

        Console::info('No sync errors recorded');

        return $this;
    }

    /**
     * Print a summary of the script's system resource usage
     *
     * Example output:
     *
     * ```
     * CPU time: 0.011s real, 0.035s user, 0.016s system; memory: 3.817MiB peak
     *
     * ```
     *
     * @return $this
     */
    final public function writeResourceUsage(int $level = Level::INFO)
    {
        [$endTime, $peakMemory, $userTime, $systemTime] = [
            hrtime(true),
            Sys::getPeakMemoryUsage(),
            ...Sys::getCpuUsage(),
        ];
        Console::print(sprintf(
            "\nCPU time: **%.3fs** real, **%.3fs** user, **%.3fs** system; memory: **%s** peak",
            ($endTime - $this->StartTime) / 1000000000,
            $userTime / 1000000,
            $systemTime / 1000000,
            Format::bytes($peakMemory, 3)
        ), $level);

        return $this;
    }

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
    final public function writeTimers(bool $includeRunning = true, ?string $type = null, int $level = Level::INFO, ?int $limit = 10)
    {
        foreach (Sys::getTimers($includeRunning, $type) as $_type => $timers) {
            $maxRuns = $maxTime = $totalTime = 0;
            $count   = count($timers);
            foreach ($timers as [$time, $runs]) {
                $totalTime += $time;
                $maxTime    = max($maxTime, $time);
                $maxRuns    = max($maxRuns, $runs);
            }
            uasort($timers, fn(array $a, array $b) => $b[0] <=> $a[0]);
            $lines[] = sprintf(
                "\nTiming: **%.3fms** recorded by **%d** %s with type '**%s**':",
                $totalTime,
                $count,
                Convert::plural($count, 'timer'),
                $_type
            );
            $timeWidth = strlen((string) ((int) $maxTime)) + 4;
            $runsWidth = strlen((string) ((int) $maxRuns)) + 2;
            if (!is_null($limit) && $limit < $count) {
                array_splice($timers, $limit);
            }
            foreach ($timers as $name => [$time, $runs]) {
                $lines[] = sprintf(
                    "  %{$timeWidth}.3fms ~~{~~%{$runsWidth}s~~}~~ ***%s***",
                    $time,
                    sprintf('*%d*', $runs),
                    $name
                );
            }
            if ($hidden = $count - count($timers)) {
                $width   = $timeWidth + $runsWidth + 6;
                $lines[] = sprintf("%{$width}s~~(and %d more)~~", '', $hidden);
            }
        }
        if ($lines ?? null) {
            Console::print(implode(PHP_EOL, $lines), $level);
        }

        return $this;
    }
}
