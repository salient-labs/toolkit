<?php declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevels;
use Lkrms\Console\Target\StreamTarget;
use Lkrms\Container\Container;
use Lkrms\Contract\IApplication;
use Lkrms\Err\Err;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Composer;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Facade\Format;
use Lkrms\Facade\Sync;
use Lkrms\Facade\Sys;
use Lkrms\Utility\Env;
use Lkrms\Utility\Test;
use Phar;
use RuntimeException;
use UnexpectedValueException;

/**
 * A service container for applications
 *
 */
class Application extends Container implements IApplication
{
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
     * @var Env
     */
    protected $Env;

    /**
     * @var string
     */
    private $BasePath;

    /**
     * @var string
     */
    private $_CachePath;

    /**
     * @var string
     */
    private $_ConfigPath;

    /**
     * @var string
     */
    private $_DataPath;

    /**
     * @var string
     */
    private $_LogPath;

    /**
     * @var string
     */
    private $_TempPath;

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

    /**
     * @var bool
     */
    private $ShutdownReportIsRegistered = false;

    private function getPath(
        string $name,
        string $parent,
        ?string $child,
        string $sourceChild,
        string $windowsChild,
        bool $create
    ): string {
        $name = "app_{$name}_path";

        if ($path = $this->Env->get($name, null)) {
            if (!Test::isAbsolutePath($path)) {
                $path = $this->BasePath . '/' . $path;
            }
            return $this->_getPath($path, $name, $create);
        }

        // If running from source, return `$this->BasePath/$sourceChild` if it
        // resolves to a writable directory
        if (!$this->inProduction()) {
            $path = "{$this->BasePath}/$sourceChild";
            if (Test::firstExistingDirectoryIsWritable($path)) {
                return $this->_getPath($path, $name, $create);
            }
        }

        $app = $this->getAppName();

        if (PHP_OS_FAMILY === 'Windows') {
            switch ($parent) {
                case self::DIR_CONFIG:
                case self::DIR_DATA:
                    $path = $this->Env->get('APPDATA');
                    break;
                case self::DIR_STATE:
                default:
                    $path = $this->Env->get('LOCALAPPDATA');
                    break;
            }

            return $this->_getPath(
                Convert::sparseToString(
                    '/', [$path, $app, $windowsChild]
                ),
                $name,
                $create
            );
        }

        if (!($home = $this->Env->home())) {
            throw new RuntimeException('Home directory not found');
        }

        switch ($parent) {
            case self::DIR_CONFIG:
                $path = $this->Env->get('XDG_CONFIG_HOME', $home . '/.config');
                break;
            case self::DIR_DATA:
                $path = $this->Env->get('XDG_DATA_HOME', $home . '/.local/share');
                break;
            case self::DIR_STATE:
            default:
                $path = $this->Env->get('XDG_CACHE_HOME', $home . '/.cache');
                break;
        }

        return $this->_getPath(
            Convert::sparseToString(
                '/', [$path, $app, $child]
            ),
            $name,
            $create
        );
    }

    private function _getPath(string $path, string $name, bool $create): string
    {
        if (!Test::isAbsolutePath($path)) {
            throw new UnexpectedValueException("Absolute path required: $name");
        }
        if ($create) {
            File::maybeCreateDirectory($path);
        }
        return $path;
    }

    final public function getCachePath(bool $create = true): string
    {
        return $this->_CachePath
            ?: ($this->_CachePath = $this->getPath('cache', self::DIR_STATE, 'cache', 'var/cache', 'cache', $create));
    }

    final public function getConfigPath(bool $create = true): string
    {
        return $this->_ConfigPath
            ?: ($this->_ConfigPath = $this->getPath('config', self::DIR_CONFIG, null, 'config', 'config', $create));
    }

    final public function getDataPath(bool $create = true): string
    {
        return $this->_DataPath
            ?: ($this->_DataPath = $this->getPath('data', self::DIR_DATA, null, 'var/lib', 'data', $create));
    }

    final public function getLogPath(bool $create = true): string
    {
        return $this->_LogPath
            ?: ($this->_LogPath = $this->getPath('log', self::DIR_STATE, 'log', 'var/log', 'log', $create));
    }

    final public function getTempPath(bool $create = true): string
    {
        return $this->_TempPath
            ?: ($this->_TempPath = $this->getPath('temp', self::DIR_STATE, 'tmp', 'var/tmp', 'tmp', $create));
    }

    public function __construct(?string $basePath = null)
    {
        $this->StartTime = hrtime(true);

        parent::__construct();

        static::setGlobalContainer($this);

        $this->Env = $this->singletonIf(Env::class)
                          ->get(Env::class);

        if ($basePath === null) {
            $basePath = $this->Env->get('app_base_path', null);
            if ($basePath === null) {
                $basePath = Composer::getRootPackagePath();
            }
        }
        if (!is_dir($basePath) ||
                ($basePath = File::realpath($basePath)) === false) {
            throw new RuntimeException('Invalid basePath: ' . $basePath);
        }
        $this->BasePath = $basePath;

        if ((!extension_loaded('Phar') || !Phar::running()) &&
                is_file($env = $this->BasePath . '/.env')) {
            $this->Env->load($env);
        }
        $this->Env->apply();

        Console::registerStdioTargets();

        if (!Err::isLoaded()) {
            Err::load();
        }
        if ($path = Composer::getPackagePath('adodb/adodb-php')) {
            Err::silencePaths($path);
        }
    }

    public function unload(): void
    {
        $this->unloadSync()
             ->unloadCache();

        // @phpstan-ignore-next-line
        $this->Env = null;

        parent::unload();
    }

    final public function getBasePath(): string
    {
        return $this->BasePath;
    }

    public function registerShutdownReport(
        int $level = Level::INFO,
        ?array $timers = ['*'],
        bool $resourceUsage = true
    ) {
        if ($this->ShutdownReportIsRegistered || (!$timers && !$resourceUsage)) {
            return $this;
        }
        register_shutdown_function(
            function () use ($level, $timers, $resourceUsage) {
                if ($timers === ['*']) {
                    $this->writeTimers($level);
                } elseif ($timers) {
                    foreach ($timers as $timer) {
                        $this->writeTimers($level, true, $timer);
                    }
                }
                if ($resourceUsage) {
                    $this->writeResourceUsage($level);
                }
            }
        );
        $this->ShutdownReportIsRegistered = true;

        return $this;
    }

    public function inProduction(): bool
    {
        return $this->Env->get('PHP_ENV', null) === 'production' ||
            (extension_loaded('Phar') && Phar::running()) ||
            !Composer::hasDevDependencies();
    }

    public function getProgramName(): string
    {
        return Sys::getProgramBasename();
    }

    final public function getAppName(): string
    {
        return preg_replace(
            // Match `git describe --long` and similar formats
            '/-v?[0-9]+(\.[0-9]+){0,3}(-[0-9]+)?(-g?[0-9a-f]+)?$/i',
            '',
            Sys::getProgramBasename('.php', '.phar')
        );
    }

    final public function env(): Env
    {
        return $this->Env;
    }

    final public function loadCache()
    {
        $cacheFile = $this->getCacheFile();
        if (Cache::isLoaded()) {
            if (Test::areSameFile($cacheFile, Cache::getFilename() ?: '')) {
                return $this;
            }
            throw new RuntimeException('Cache already loaded');
        }
        Cache::load($cacheFile);
        return $this;
    }

    final public function loadCacheIfExists()
    {
        return file_exists($this->getCacheFile(false))
            ? $this->loadCache()
            : $this;
    }

    final public function unloadCache()
    {
        if (!Cache::isLoaded() ||
                !Test::areSameFile($this->getCacheFile(false), Cache::getFilename() ?: '')) {
            return $this;
        }
        Cache::close();
        return $this;
    }

    final public function logConsoleMessages(?bool $debug = null, ?string $name = null)
    {
        $name = $name ? basename($name, '.log') : $this->getAppName();
        if (!($this->LogTargets[$name] ?? null)) {
            $this->LogTargets[$name] = $target = StreamTarget::fromPath($this->getLogPath() . "/$name.log");
            Console::registerTarget($target, ConsoleLevels::ALL);
        }
        if (($debug || (is_null($debug) && $this->Env->debug())) &&
                !($this->DebugLogTargets[$name] ?? null)) {
            $this->DebugLogTargets[$name] = $target = StreamTarget::fromPath($this->getLogPath() . "/$name.debug.log");
            Console::registerTarget($target, ConsoleLevels::ALL_DEBUG);
        }

        return $this;
    }

    final public function loadSync(?string $command = null, ?array $arguments = null)
    {
        $syncDb = $this->getDataPath() . '/sync.db';

        if (!Sync::isLoaded()) {
            Sync::load(
                $syncDb,
                is_null($command) ? Sys::getProgramName($this->BasePath) : $command,
                (is_null($arguments)
                    ? (PHP_SAPI == 'cli'
                        ? array_slice($_SERVER['argv'], 1)
                        : ['_GET' => $_GET, '_POST' => $_POST])
                    : $arguments)
            );
        } elseif (!Test::areSameFile($syncDb, $file = Sync::getFilename() ?: '')) {
            throw new RuntimeException("Sync database already loaded: $file");
        }

        return $this;
    }

    final public function syncNamespace(string $prefix, string $uri, string $namespace, ?string $resolver = null)
    {
        if (!Sync::isLoaded()) {
            throw new RuntimeException('Sync database not loaded');
        }
        Sync::namespace($prefix, $uri, $namespace, $resolver);

        return $this;
    }

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

    final public function writeResourceUsage(int $level = Level::INFO)
    {
        [$endTime, $peakMemory, $userTime, $systemTime] = [
            hrtime(true),
            Sys::getPeakMemoryUsage(),
            ...Sys::getCpuUsage(),
        ];
        Console::print(sprintf(
            "\nCPU time: **%.3fs** elapsed, **%.3fs** user, **%.3fs** system; memory: **%s** peak",
            ($endTime - $this->StartTime) / 1000000000,
            $userTime / 1000000,
            $systemTime / 1000000,
            Format::bytes($peakMemory, 3)
        ), $level);

        return $this;
    }

    final public function writeTimers(
        int $level = Level::INFO,
        bool $includeRunning = true,
        ?string $type = null,
        ?int $limit = 10
    ) {
        foreach (Sys::getTimers($includeRunning, $type) as $_type => $timers) {
            $maxRuns = $maxTime = $totalTime = 0;
            $count = count($timers);
            foreach ($timers as [$time, $runs]) {
                $totalTime += $time;
                $maxTime = max($maxTime, $time);
                $maxRuns = max($maxRuns, $runs);
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
                $width = $timeWidth + $runsWidth + 6;
                $lines[] = sprintf("%{$width}s~~(and %d more)~~", '', $hidden);
            }
        }
        if ($lines ?? null) {
            Console::print(implode("\n", $lines), $level);
        }

        return $this;
    }

    private function getCacheFile(bool $create = true): string
    {
        return $this->getCachePath($create) . '/cache.db';
    }
}
