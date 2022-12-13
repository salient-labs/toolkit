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
use RuntimeException;

/**
 * A service container for applications
 *
 * Typically accessed via the {@see \Lkrms\Facade\App} facade.
 *
 * @property-read string $BasePath
 * @property-read string $CachePath
 * @property-read string $DataPath
 * @property-read string $LogPath
 * @property-read string $TempPath
 */
class AppContainer extends Container implements IReadable
{
    use TReadable;

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

    private function getPath(string $name, string $default): string
    {
        $path = ($path = Env::get($name, ''))
            ? (Test::isAbsolutePath($path) ? $path : $this->BasePath . '/' . $path)
            : $this->BasePath . '/' . $default;
        File::maybeCreateDirectory($path);

        return $path;
    }

    final protected function _getCachePath(): string
    {
        return $this->_CachePath
            ?: ($this->_CachePath = $this->getPath('app_cache_path', 'var/cache'));
    }

    final protected function _getDataPath(): string
    {
        return $this->_DataPath
            ?: ($this->_DataPath = $this->getPath('app_data_path', 'var/lib'));
    }

    final protected function _getLogPath(): string
    {
        return $this->_LogPath
            ?: ($this->_LogPath = $this->getPath('app_log_path', 'var/log'));
    }

    final protected function _getTempPath(): string
    {
        return $this->_TempPath
            ?: ($this->_TempPath = $this->getPath('app_temp_path', 'var/tmp'));
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
            $basePath = Env::get('app_base_path', '') ?: Composer::getRootPackagePath();
        }

        if (!is_dir($basePath) ||
                ($this->BasePath = realpath($basePath)) === false) {
            throw new RuntimeException('Invalid basePath: ' . $basePath);
        }

        if (file_exists($env = $this->BasePath . '/.env')) {
            Env::loadFile($env);
        } else {
            Env::apply();
        }

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
        $name = $name ? basename($name, '.log') : Sys::getProgramBasename('.php');
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
