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
 * @property-read string $BasePath   Environment variable: app_base_path
 * @property-read string $CachePath  Environment variable: app_cache_path
 * @property-read string $ConfigPath Environment variable: app_config_path
 * @property-read string $DataPath   Environment variable: app_data_path
 * @property-read string $LogPath    Environment variable: app_log_path
 * @property-read string $TempPath   Environment variable: app_temp_path
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

    private function getPath(string $name, string $parent, ?string $child, string $sourceChild, string $windowsChild): string
    {
        $name = "app_{$name}_path";
        if ($path = Env::get($name, null)) {
            return $this->_getPath($path, $name);
        }

        // If running from source, return `$this->BasePath/$sourceChild` if it
        // resolves to a writable directory
        if (!Phar::running()) {
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
     * Return the basename of the file used to run the script after removing PHP
     * file extensions
     *
     */
    final public function getAppName(): string
    {
        return Sys::getProgramBasename('.php', '.phar');
    }

    /**
     * @return $this
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
     * @return $this
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
    final public function logConsoleMessages(?bool $debug = true, ?string $name = null)
    {
        $name = $name ? basename($name, '.log') : $this->getAppName();
        Console::registerTarget(StreamTarget::fromPath($this->LogPath . "/$name.log"), ConsoleLevels::ALL);
        if ($debug || (is_null($debug) && Env::debug())) {
            Console::registerTarget(StreamTarget::fromPath($this->LogPath . "/$name.debug.log"), ConsoleLevels::ALL_DEBUG);
        }

        return $this;
    }

    /**
     * @return $this
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
     * @return $this
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
                "\n" . $errors, null, false
            );

            return $this;
        }

        Console::info('No sync errors recorded');

        return $this;
    }

    /**
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
            'CPU time: **%.3fs** real, **%.3fs** user, **%.3fs** system; memory: **%s** peak',
            ($endTime - $this->StartTime) / 1000000000,
            $userTime / 1000000,
            $systemTime / 1000000,
            Format::bytes($peakMemory, 3)
        ), $level);

        return $this;
    }

    /**
     * @return $this
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
                "Timing: **%.3fms** recorded by **%d** %s with type '**%s**':",
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
