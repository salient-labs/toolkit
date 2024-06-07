<?php declare(strict_types=1);

namespace Salient\Container;

use Salient\Cache\CacheStore;
use Salient\Console\Target\StreamTarget;
use Salient\Contract\Cache\CacheStoreInterface;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Container\ApplicationInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Contract\Sync\SyncClassResolverInterface;
use Salient\Core\Exception\LogicException;
use Salient\Core\Facade\Cache;
use Salient\Core\Facade\Config;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Err;
use Salient\Core\Facade\Profile;
use Salient\Core\Facade\Sync;
use Salient\Sync\SyncStore;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\Exception\InvalidEnvironmentException;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Format;
use Salient\Utility\Inflect;
use Salient\Utility\Package;
use Salient\Utility\Regex;
use Salient\Utility\Sys;
use Phar;

/**
 * A service container for applications
 */
class Application extends Container implements ApplicationInterface
{
    private const DIR_CONFIG = 'CONFIG';
    private const DIR_DATA = 'DATA';
    private const DIR_STATE = 'STATE';

    private string $AppName;
    private string $BasePath;
    private string $WorkingDirectory;
    private bool $RunningFromSource;

    // @phpstan-ignore-next-line
    private ?string $CachePath;

    // @phpstan-ignore-next-line
    private ?string $ConfigPath;

    // @phpstan-ignore-next-line
    private ?string $DataPath;

    // @phpstan-ignore-next-line
    private ?string $LogPath;

    // @phpstan-ignore-next-line
    private ?string $TempPath;
    /** @var StreamTarget[] */
    private array $LogTargets = [];
    /** @var StreamTarget[] */
    private array $DebugLogTargets = [];
    /** @var int|float|null */
    private static $StartTime;
    /** @var Level::* */
    private static int $ShutdownReportLevel;
    /** @var string[]|string|null */
    private static $ShutdownReportMetricGroups;
    private static bool $ShutdownReportResourceUsage;
    private static bool $ShutdownReportIsRegistered = false;

    /**
     * Get a platform- and environment-aware writable directory that satisfies
     * the given criteria
     *
     * @param string $name The internal name of the directory, e.g. `"cache"`.
     * Used to check for environment-supplied values (e.g. `"app_cache_path"`)
     * and in user feedback.
     * @param Application::DIR_* $parent Either `"CONFIG"`, `"DATA"`, or
     * `"STATE"`. Used in production to determine which top-level directory in
     * `$HOME` or the user's profile is appropriate for the directory.
     * @param string|null $child Provided if the directory should be created
     * below the main directory created for the application in `$parent` (e.g.
     * `"tmp"` might resolve to `"$HOME/.cache/<app_name>/tmp"`).
     * @param string $sourceChild A path relative to the application's base
     * path. Used when running from source in a non-production environment.
     * @param string $windowsChild On Windows, the value of `$child` is ignored
     * and `$windowsChild` is used for the same purpose.
     */
    private function getPath(
        string $name,
        string $parent,
        ?string $child,
        string $sourceChild,
        string $windowsChild,
        bool $create,
        ?string &$save
    ): string {
        $name = "app_{$name}_path";

        $path = Env::get($name, null);
        if ($path !== null) {
            if (trim($path) === '') {
                throw new InvalidEnvironmentException(
                    sprintf('%s disabled in this environment', $name)
                );
            }
            if (!File::isAbsolute($path)) {
                $path = "{$this->BasePath}/$path";
            }
            return $this->checkPath($path, $name, $create, $save);
        }

        // If running from source, return `"{$this->BasePath}/$sourceChild"` if
        // it resolves to a writable directory
        if (!$this->isProduction()) {
            $path = "{$this->BasePath}/$sourceChild";
            if (File::isCreatable($path)) {
                return $this->checkPath($path, $name, $create, $save);
            }
        }

        $app = $this->getAppName();

        if (Sys::isWindows()) {
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

            return $this->checkPath(
                Arr::implode('/', [$path, $app, $windowsChild]),
                $name,
                $create,
                $save,
            );
        }

        $home = Env::getHomeDir();
        if ($home === null || !is_dir($home)) {
            throw new InvalidEnvironmentException('Home directory not found');
        }

        switch ($parent) {
            case self::DIR_CONFIG:
                $path = Env::get('XDG_CONFIG_HOME', "{$home}/.config");
                break;

            case self::DIR_DATA:
                $path = Env::get('XDG_DATA_HOME', "{$home}/.local/share");
                break;

            case self::DIR_STATE:
            default:
                $path = Env::get('XDG_CACHE_HOME', "{$home}/.cache");
                break;
        }

        return $this->checkPath(
            Arr::implode('/', [$path, $app, $child]),
            $name,
            $create,
            $save,
        );
    }

    private function checkPath(string $path, string $name, bool $create, ?string &$save): string
    {
        if (!File::isAbsolute($path)) {
            throw new InvalidEnvironmentException(
                sprintf('Absolute path required: %s', $name)
            );
        }

        if ($create) {
            File::createDir($path);
            $save = $path;
        }

        return $path;
    }

    /**
     * @inheritDoc
     */
    final public function getCachePath(bool $create = true): string
    {
        return $this->CachePath
            ?? ($this->getPath('cache', self::DIR_STATE, 'cache', 'var/cache', 'cache', $create, $this->CachePath));
    }

    /**
     * @inheritDoc
     */
    final public function getConfigPath(bool $create = true): string
    {
        return $this->ConfigPath
            ?? ($this->getPath('config', self::DIR_CONFIG, null, 'config', 'config', $create, $this->ConfigPath));
    }

    /**
     * @inheritDoc
     */
    final public function getDataPath(bool $create = true): string
    {
        return $this->DataPath
            ?? ($this->getPath('data', self::DIR_DATA, null, 'var/lib', 'data', $create, $this->DataPath));
    }

    /**
     * @inheritDoc
     */
    final public function getLogPath(bool $create = true): string
    {
        return $this->LogPath
            ?? ($this->getPath('log', self::DIR_STATE, 'log', 'var/log', 'log', $create, $this->LogPath));
    }

    /**
     * @inheritDoc
     */
    final public function getTempPath(bool $create = true): string
    {
        return $this->TempPath
            ?? ($this->getPath('temp', self::DIR_STATE, 'tmp', 'var/tmp', 'tmp', $create, $this->TempPath));
    }

    /**
     * Creates a new Application object
     *
     * If `$basePath` is `null`, the value of environment variable
     * `app_base_path` is used if present, otherwise the path of the root
     * package is used.
     *
     * If `$appName` is `null`, the basename of the file used to run the script
     * is used after removing common PHP file extensions and recognised version
     * numbers.
     *
     * If `$configDir` exists and is a directory, it is passed to
     * {@see Config::loadDirectory()} after `.env` files are loaded and applied.
     *
     * @api
     *
     * @param int-mask-of<Env::APPLY_*> $envFlags Values to apply from the
     * environment to the running script.
     * @param string|null $configDir A path relative to the application's base
     * path, or `null` if configuration files should not be loaded.
     */
    public function __construct(
        ?string $basePath = null,
        ?string $appName = null,
        int $envFlags = Env::APPLY_ALL,
        ?string $configDir = 'config'
    ) {
        if (!isset(self::$StartTime)) {
            self::$StartTime = hrtime(true);
        }

        parent::__construct();

        static::setGlobalContainer($this);

        $this->AppName = $appName
            ?? Regex::replace(
                // Match `git describe --long` and similar formats
                '/-v?[0-9]+(\.[0-9]+){0,3}(-[0-9]+)?(-g?[0-9a-f]+)?$/i',
                '',
                Sys::getProgramBasename('.php', '.phar')
            );

        $explicitBasePath = true;
        $defaultBasePath = false;
        if ($basePath === null) {
            $explicitBasePath = false;
            $basePath = Env::get('app_base_path', null);
            if ($basePath === null) {
                $defaultBasePath = true;
                $basePath = Package::path();
            }
        }

        if (!is_dir($basePath)) {
            $exception = $explicitBasePath || $defaultBasePath
                ? FilesystemErrorException::class
                : InvalidEnvironmentException::class;
            throw new $exception(sprintf('Invalid basePath: %s', $basePath));
        }

        $basePath = File::realpath($basePath);

        $this->BasePath = $basePath;

        $this->WorkingDirectory = File::getcwd();

        $this->RunningFromSource =
            !extension_loaded('Phar')
            || Phar::running() === '';

        if ($this->RunningFromSource) {
            $files = [];
            $env = Env::getEnvironment();
            if ($env !== null) {
                $files[] = "{$this->BasePath}/.env.{$env}";
            }
            $files[] = "{$this->BasePath}/.env";
            foreach ($files as $file) {
                if (is_file($file)) {
                    Env::loadFiles($file);
                    break;
                }
            }
        }

        Env::apply($envFlags);

        Console::registerStdioTargets();

        Err::register();

        $adodb = Package::packagePath('adodb/adodb-php');
        if ($adodb !== null) {
            Err::silencePath($adodb);
        }

        if ($configDir !== null && $configDir !== '') {
            if (!File::isAbsolute($configDir)) {
                $configDir = "{$this->BasePath}/{$configDir}";
            }
            if (is_dir($configDir)) {
                Config::loadDirectory($configDir);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        $this->stopSync()->stopCache();
        parent::unload();
    }

    /**
     * @inheritDoc
     */
    final public function getBasePath(): string
    {
        return $this->BasePath;
    }

    /**
     * @inheritDoc
     */
    final public function getWorkingDirectory(): string
    {
        return $this->WorkingDirectory;
    }

    /**
     * @inheritDoc
     */
    final public function restoreWorkingDirectory()
    {
        if (File::getcwd() !== $this->WorkingDirectory) {
            File::chdir($this->WorkingDirectory);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function setWorkingDirectory(?string $directory = null)
    {
        $this->WorkingDirectory = $directory ?? File::getcwd();

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function registerShutdownReport(
        int $level = Level::INFO,
        $groups = null,
        bool $resourceUsage = true
    ) {
        self::$ShutdownReportLevel = $level;
        self::$ShutdownReportMetricGroups = $groups;
        self::$ShutdownReportResourceUsage = $resourceUsage;

        if (self::$ShutdownReportIsRegistered) {
            return $this;
        }

        register_shutdown_function(
            static function () {
                /** @var int&Level::* */
                $level = self::$ShutdownReportLevel;
                self::doReportMetrics($level, true, self::$ShutdownReportMetricGroups, 10);
                if (self::$ShutdownReportResourceUsage) {
                    self::doReportResourceUsage($level);
                }
            }
        );

        self::$ShutdownReportIsRegistered = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isProduction(): bool
    {
        $env = Env::getEnvironment();

        return
            $env === 'production'
            || ($env === null
                && (!$this->RunningFromSource
                    || !Package::hasDevPackages()));
    }

    /**
     * @inheritDoc
     */
    final public function getAppName(): string
    {
        return $this->AppName;
    }

    /**
     * @inheritDoc
     */
    final public function startCache()
    {
        if (Cache::isLoaded()) {
            if ($this->checkCache(Cache::getInstance())) {
                return $this;
            }
            throw new LogicException('Cache store already started');
        }

        Cache::load(new CacheStore($this->getCacheDb()));

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function resumeCache()
    {
        return file_exists($this->getCacheDb(false))
            ? $this->startCache()
            : $this;
    }

    /**
     * @inheritDoc
     */
    final public function stopCache()
    {
        if (
            !Cache::isLoaded()
            || !$this->checkCache($cache = Cache::getInstance())
        ) {
            return $this;
        }

        /** @var CacheStore $cache */
        $cache->close();

        return $this;
    }

    private function checkCache(CacheStoreInterface $cache): bool
    {
        return $cache instanceof CacheStore
            && File::same($this->getCacheDb(false), $cache->getFilename());
    }

    /**
     * @inheritDoc
     */
    final public function logOutput(?string $name = null, ?bool $debug = null)
    {
        $name = $name === null
            ? $this->getAppName()
            : basename($name, '.log');

        if (!isset($this->LogTargets[$name])) {
            $target = StreamTarget::fromPath($this->getLogPath() . "/$name.log");
            Console::registerTarget($target, LevelGroup::ALL_EXCEPT_DEBUG);
            $this->LogTargets[$name] = $target;
        }

        if (($debug || ($debug === null && Env::getDebug()))
                && !isset($this->DebugLogTargets[$name])) {
            $target = StreamTarget::fromPath($this->getLogPath() . "/$name.debug.log");
            Console::registerTarget($target, LevelGroup::ALL);
            $this->DebugLogTargets[$name] = $target;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function startSync(?string $command = null, ?array $arguments = null)
    {
        $syncDb = $this->getSyncDb();

        if (Sync::isLoaded()) {
            $store = Sync::getInstance();
            if ($store instanceof SyncStore) {
                $file = $store->getFilename();
                if (File::same($syncDb, $file)) {
                    return $this;
                }
            }
            throw new LogicException(sprintf(
                'Entity store already started: %s',
                $file ?? get_class($store),
            ));
        }

        Sync::load(new SyncStore(
            $syncDb,
            $command === null
                ? Sys::getProgramName($this->BasePath)
                : $command,
            ($arguments === null
                ? (\PHP_SAPI == 'cli'
                    ? array_slice($_SERVER['argv'], 1)
                    : ['_GET' => $_GET, '_POST' => $_POST])
                : $arguments)
        ));

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function syncNamespace(
        string $prefix,
        string $uri,
        string $namespace,
        ?SyncClassResolverInterface $resolver = null
    ) {
        if (!Sync::isLoaded()) {
            throw new LogicException('Entity store not started');
        }
        Sync::registerNamespace($prefix, $uri, $namespace, $resolver);

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function stopSync()
    {
        if (!Sync::isLoaded()) {
            return $this;
        }

        $store = Sync::getInstance();
        if (
            !$store instanceof SyncStore
            || !File::same($this->getSyncDb(false), $store->getFilename())
        ) {
            return $this;
        }

        $store->close();

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function reportResourceUsage(int $level = Level::INFO)
    {
        self::doReportResourceUsage($level);
        return $this;
    }

    /**
     * @param Level::* $level
     */
    private static function doReportResourceUsage(int $level): void
    {
        [$endTime, $peakMemory, $userTime, $systemTime] = [
            hrtime(true),
            Sys::getPeakMemoryUsage(),
            ...Sys::getCpuUsage(),
        ];

        Console::print(
            "\n" . sprintf(
                'CPU time: **%.3fs** elapsed, **%.3fs** user, **%.3fs** system; memory: **%s** peak',
                ($endTime - self::$StartTime) / 1000000000,
                $userTime / 1000000,
                $systemTime / 1000000,
                Format::bytes($peakMemory)
            ),
            $level,
            MessageType::UNFORMATTED,
        );
    }

    /**
     * @inheritDoc
     */
    final public function reportMetrics(
        int $level = Level::INFO,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
    ) {
        self::doReportMetrics($level, $includeRunningTimers, $groups, $limit);
        return $this;
    }

    /**
     * @param Level::* $level
     * @param string[]|string|null $groups
     */
    private static function doReportMetrics(
        int $level,
        bool $includeRunningTimers,
        $groups,
        ?int $limit
    ): void {
        $groupCounters = Profile::getCounters($groups);
        foreach ($groupCounters as $group => $counters) {
            // Sort by counter value, in descending order
            uasort($counters, fn(int $a, int $b) => $b <=> $a);

            $maxValue = $totalValue = 0;
            $count = count($counters);
            foreach ($counters as $value) {
                $totalValue += $value;
                $maxValue = max($maxValue, $value);
            }

            if ($limit !== null && $limit < $count) {
                $counters = array_slice($counters, 0, $limit, true);
            }

            $lines = [];
            $lines[] = Inflect::format(
                $totalValue,
                "Metrics: **{{#}}** {{#:event}} recorded by %s in group '**%s**':",
                Inflect::format($count, '**{{#}}** {{#:counter}}'),
                $group,
            );

            $valueWidth = strlen((string) $maxValue);
            $format = "  %{$valueWidth}d ***%s***";
            foreach ($counters as $name => $value) {
                $lines[] = sprintf(
                    $format,
                    $value,
                    $name,
                );
            }

            if ($hidden = $count - count($counters)) {
                $width = $valueWidth + 3;
                $lines[] = sprintf("%{$width}s~~(and %d more)~~", '', $hidden);
            }

            $report[] = implode("\n", $lines);
        }

        $groupTimers = Profile::getTimers($includeRunningTimers, $groups);
        foreach ($groupTimers as $group => $timers) {
            // Sort by milliseconds elapsed, in descending order
            uasort($timers, fn(array $a, array $b) => $b[0] <=> $a[0]);

            $maxRuns = $maxTime = $totalTime = 0;
            $count = count($timers);
            foreach ($timers as [$time, $runs]) {
                $totalTime += $time;
                $maxTime = max($maxTime, $time);
                $maxRuns = max($maxRuns, $runs);
            }

            if ($limit !== null && $limit < $count) {
                $timers = array_slice($timers, 0, $limit, true);
            }

            $lines = [];
            $lines[] = Inflect::format(
                $count,
                "Metrics: **%.3fms** recorded by **{{#}}** {{#:timer}} in group '**%s**':",
                $totalTime,
                $group,
            );

            $timeWidth = strlen((string) (int) $maxTime) + 4;
            $runsWidth = strlen((string) $maxRuns) + 2;
            $format = "  %{$timeWidth}.3fms ~~{~~%{$runsWidth}s~~}~~ ***%s***";
            foreach ($timers as $name => [$time, $runs]) {
                $lines[] = sprintf(
                    $format,
                    $time,
                    sprintf('*%d*', $runs),
                    $name,
                );
            }

            if ($hidden = $count - count($timers)) {
                $width = $timeWidth + $runsWidth + 6;
                $lines[] = sprintf("%{$width}s~~(and %d more)~~", '', $hidden);
            }

            $report[] = implode("\n", $lines);
        }

        if (isset($report)) {
            Console::print(
                "\n" . implode("\n\n", $report),
                $level,
                MessageType::UNFORMATTED,
            );
        }
    }

    private function getCacheDb(bool $create = true): string
    {
        return $this->getCachePath($create) . '/cache.db';
    }

    private function getSyncDb(bool $create = true): string
    {
        return $this->getDataPath($create) . '/sync.db';
    }
}
