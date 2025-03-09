<?php declare(strict_types=1);

namespace Salient\Container;

use Salient\Cache\CacheStore;
use Salient\Console\Target\StreamTarget;
use Salient\Contract\Cache\CacheInterface;
use Salient\Contract\Container\ApplicationInterface;
use Salient\Contract\Curler\Event\CurlerEvent;
use Salient\Contract\Curler\Event\CurlRequestEvent;
use Salient\Contract\Curler\Event\ResponseCacheHitEvent;
use Salient\Contract\Sync\SyncNamespaceHelperInterface;
use Salient\Core\Facade\Cache;
use Salient\Core\Facade\Config;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Err;
use Salient\Core\Facade\Event;
use Salient\Core\Facade\Profile;
use Salient\Core\Facade\Sync;
use Salient\Curler\CurlerHarRecorder;
use Salient\Sync\SyncStore;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\Exception\InvalidEnvironmentException;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Format;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Package;
use Salient\Utility\Regex;
use Salient\Utility\Sys;
use DateTime;
use DateTimeZone;
use LogicException;
use Phar;
use RuntimeException;

/**
 * A service container for applications
 */
class Application extends Container implements ApplicationInterface
{
    private const PATH_CACHE = 0;
    private const PATH_CONFIG = 1;
    private const PATH_DATA = 2;
    private const PATH_LOG = 3;
    private const PATH_TEMP = 4;
    private const PARENT_CONFIG = 0;
    private const PARENT_DATA = 1;
    private const PARENT_STATE = 2;

    /**
     * [ Index => [ name, parent, child, Windows child, source child ], ... ]
     */
    private const PATHS = [
        self::PATH_CACHE => ['cache', self::PARENT_STATE, 'cache', 'cache', 'var/cache'],
        self::PATH_CONFIG => ['config', self::PARENT_CONFIG, null, 'config', 'var/lib/config'],
        self::PATH_DATA => ['data', self::PARENT_DATA, null, 'data', 'var/lib/data'],
        self::PATH_LOG => ['log', self::PARENT_STATE, 'log', 'log', 'var/log'],
        self::PATH_TEMP => ['temp', self::PARENT_STATE, 'tmp', 'tmp', 'var/tmp'],
    ];

    private string $AppName;
    private string $BasePath;
    private string $WorkingDirectory;
    private bool $RunningFromSource;

    /**
     * @var array<self::PATH_*,string|null>
     */
    private array $Paths = [
        self::PATH_CACHE => null,
        self::PATH_CONFIG => null,
        self::PATH_DATA => null,
        self::PATH_LOG => null,
        self::PATH_TEMP => null,
    ];

    private bool $OutputLogIsRegistered = false;
    private ?int $HarListenerId = null;
    private ?CurlerHarRecorder $HarRecorder = null;
    private string $HarFilename;

    // --

    /** @var Console::LEVEL_* */
    private static int $ShutdownReportLevel;
    private static bool $ShutdownReportResourceUsage;
    private static bool $ShutdownReportRunningTimers;
    /** @var string[]|string|null */
    private static $ShutdownReportMetricGroups;
    private static ?int $ShutdownReportMetricLimit;
    private static bool $ShutdownReportIsRegistered = false;

    /**
     * Creates a new service container
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
     * {@see Config::loadDirectory()} after `.env` files are loaded and values
     * are applied from the environment to the running script.
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
        parent::__construct();

        static::setGlobalContainer($this);

        $this->AppName = $appName ?? Regex::replace(
            '/-v?[0-9]+(\.[0-9]+){0,3}(-[0-9]+)?(-g?[0-9a-f]+)?$/i',
            '',
            Sys::getProgramBasename('.php', '.phar'),
        );

        if ($basePath === null) {
            $explicitBasePath = false;
            $basePath = Env::get('app_base_path', null);
            if ($basePath === null) {
                $basePath = Package::path();
                $defaultBasePath = true;
            } else {
                $defaultBasePath = false;
            }
        } else {
            $explicitBasePath = true;
            $defaultBasePath = false;
        }

        if (!is_dir($basePath)) {
            $exception = $explicitBasePath || $defaultBasePath
                ? FilesystemErrorException::class
                : InvalidEnvironmentException::class;
            throw new $exception(sprintf('Invalid base path: %s', $basePath));
        }

        $this->BasePath = File::realpath($basePath);

        $this->WorkingDirectory = File::getcwd();

        $this->RunningFromSource = !extension_loaded('Phar')
            || Phar::running() === '';

        if ($this->RunningFromSource) {
            $files = [];
            $env = Env::getEnvironment();
            if ($env !== null) {
                $files[] = $this->BasePath . '/.env.' . $env;
            }
            $files[] = $this->BasePath . '/.env';
            foreach ($files as $file) {
                if (is_file($file)) {
                    Env::loadFiles($file);
                    break;
                }
            }
        }

        if ($envFlags) {
            Env::apply($envFlags);
        }

        Console::registerStdioTargets();

        Err::register();

        $adodb = Package::getPackagePath('adodb/adodb-php');
        if ($adodb !== null) {
            Err::silencePath($adodb);
        }

        if ($configDir !== null) {
            if (!File::isAbsolute($configDir)) {
                $configDir = $this->BasePath . '/' . $configDir;
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
        $this->stopCache();
        $this->stopSync();
        if ($this->HarListenerId !== null) {
            Event::removeListener($this->HarListenerId);
            $this->HarListenerId = null;
        } elseif ($this->HarRecorder) {
            $this->HarRecorder->close();
            $this->HarRecorder = null;
            unset($this->HarFilename);
        }
        parent::unload();
    }

    /**
     * @inheritDoc
     */
    final public function getName(): string
    {
        return $this->AppName;
    }

    /**
     * @inheritDoc
     */
    public function isRunningInProduction(): bool
    {
        $env = Env::getEnvironment();

        return $env === 'production'
            || ($env === null && (
                !$this->RunningFromSource
                || !Package::hasDevPackages()
            ));
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
    final public function getCachePath(bool $create = true): string
    {
        return $this->getPath(self::PATH_CACHE, $create);
    }

    /**
     * @inheritDoc
     */
    final public function getConfigPath(bool $create = true): string
    {
        return $this->getPath(self::PATH_CONFIG, $create);
    }

    /**
     * @inheritDoc
     */
    final public function getDataPath(bool $create = true): string
    {
        return $this->getPath(self::PATH_DATA, $create);
    }

    /**
     * @inheritDoc
     */
    final public function getLogPath(bool $create = true): string
    {
        return $this->getPath(self::PATH_LOG, $create);
    }

    /**
     * @inheritDoc
     */
    final public function getTempPath(bool $create = true): string
    {
        return $this->getPath(self::PATH_TEMP, $create);
    }

    /**
     * @param self::PATH_* $index
     */
    private function getPath(int $index, bool $create): string
    {
        if ($this->Paths[$index] !== null) {
            return $this->Paths[$index];
        }

        [$name, $parent, $child, $winChild, $srcChild] = self::PATHS[$index];
        $varName = sprintf('app_%s_path', $name);

        $path = Env::get($varName, null);
        if ($path !== null) {
            if (trim($path) === '') {
                throw new InvalidEnvironmentException(sprintf(
                    'Directory disabled (empty %s in environment)',
                    $varName,
                ));
            }
            if (!File::isAbsolute($path)) {
                $path = $this->BasePath . '/' . $path;
            }
        } elseif (
            !$this->isRunningInProduction()
            && File::isCreatable($this->BasePath . '/' . $srcChild)
        ) {
            $path = $this->BasePath . '/' . $srcChild;
        } elseif (Sys::isWindows()) {
            switch ($parent) {
                case self::PARENT_CONFIG:
                case self::PARENT_DATA:
                    $path = Env::get('APPDATA');
                    break;

                case self::PARENT_STATE:
                    $path = Env::get('LOCALAPPDATA');
                    break;
            }

            $path = Arr::implode('/', [$path, $this->AppName, $winChild], '');
        } else {
            $home = Env::getHomeDir();
            if ($home === null || !is_dir($home)) {
                throw new InvalidEnvironmentException('Home directory not found');
            }

            switch ($parent) {
                case self::PARENT_CONFIG:
                    $path = Env::get('XDG_CONFIG_HOME', $home . '/.config');
                    break;

                case self::PARENT_DATA:
                    $path = Env::get('XDG_DATA_HOME', $home . '/.local/share');
                    break;

                case self::PARENT_STATE:
                    $path = Env::get('XDG_CACHE_HOME', $home . '/.cache');
                    break;
            }

            $path = Arr::implode('/', [$path, $this->AppName, $child], '');
        }

        if (!File::isAbsolute($path)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf(
                'Absolute path to %s directory required',
                $name,
            ));
            // @codeCoverageIgnoreEnd
        }

        if ($create) {
            File::createDir($path);
            $this->Paths[$index] = $path;
        }

        return $path;
    }

    /**
     * @inheritDoc
     */
    final public function logOutput(?string $name = null, ?bool $debug = null)
    {
        if ($this->OutputLogIsRegistered) {
            throw new LogicException('Output log already registered');
        }

        $name ??= $this->AppName;
        $target = StreamTarget::fromPath($this->getLogPath() . "/$name.log");
        Console::registerTarget($target, Console::LEVELS_ALL_EXCEPT_DEBUG);

        if ($debug || ($debug === null && Env::getDebug())) {
            $target = StreamTarget::fromPath($this->getLogPath() . "/$name.debug.log");
            Console::registerTarget($target, Console::LEVELS_ALL);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function exportHar(
        ?string $name = null,
        ?string $creatorName = null,
        ?string $creatorVersion = null,
        $uuid = null
    ) {
        if ($this->HarListenerId !== null || $this->HarRecorder) {
            throw new LogicException('HAR recorder already started');
        }

        $this->HarListenerId = Event::getInstance()->listen(function (
            CurlerEvent $event
        ) use ($name, $creatorName, $creatorVersion, $uuid): void {
            if (
                !$event instanceof CurlRequestEvent
                && !$event instanceof ResponseCacheHitEvent
            ) {
                return;
            }

            $uuid ??= fn() =>
                Sync::isLoaded() && Sync::runHasStarted()
                    ? Sync::getRunUuid()
                    : Get::uuid();

            $filename = sprintf(
                '%s/har/%s-%s-%s.har',
                $this->getLogPath(),
                $name ?? $this->AppName,
                (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d-His.v'),
                Get::value($uuid),
            );

            if (file_exists($filename)) {
                throw new RuntimeException(sprintf('File already exists: %s', $filename));
            }

            File::createDir(dirname($filename));
            File::create($filename, 0600);

            $recorder = new CurlerHarRecorder(
                $filename,
                $creatorName,
                $creatorVersion,
            );
            $recorder->start($event);

            /** @var int */
            $id = $this->HarListenerId;
            Event::removeListener($id);
            $this->HarListenerId = null;
            $this->HarRecorder = $recorder;
            $this->HarFilename = $filename;
        });

        return $this;
    }

    /**
     * @phpstan-impure
     */
    final public function getHarFilename(): ?string
    {
        if ($this->HarListenerId === null && !$this->HarRecorder) {
            throw new LogicException('HAR recorder not started');
        }

        return $this->HarRecorder
            ? $this->HarFilename
            : null;
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
     * Start a cache for the application and make it the global cache if started
     * previously, otherwise do nothing
     *
     * @internal
     *
     * @return $this
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
            Cache::isLoaded()
            && $this->checkCache($cache = Cache::getInstance())
        ) {
            $cache->close();
        }
        return $this;
    }

    private function checkCache(CacheInterface $cache): bool
    {
        return $cache instanceof CacheStore
            && File::same($this->getCacheDb(false), $cache->getFilename());
    }

    private function getCacheDb(bool $create = true): string
    {
        return $this->getCachePath($create) . '/cache.db';
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

        if ($arguments === null && $command === null) {
            if (\PHP_SAPI === 'cli') {
                /** @var string[] */
                $args = $_SERVER['argv'];
                $arguments = array_slice($args, 1);
            } else {
                $arguments = [Json::encode([
                    '_GET' => $_GET,
                    '_POST' => $_POST,
                ])];
            }
        }

        Sync::load(new SyncStore(
            $syncDb,
            $command ?? Sys::getProgramName($this->BasePath),
            $arguments ?? [],
        ));

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function stopSync()
    {
        if (
            Sync::isLoaded()
            && ($store = Sync::getInstance()) instanceof SyncStore
            && File::same($this->getSyncDb(false), $store->getFilename())
        ) {
            $store->close();
        }
        return $this;
    }

    private function getSyncDb(bool $create = true): string
    {
        return $this->getDataPath($create) . '/sync.db';
    }

    /**
     * @inheritDoc
     */
    final public function sync(
        string $prefix,
        string $uri,
        string $namespace,
        ?SyncNamespaceHelperInterface $helper = null
    ) {
        if (!Sync::isLoaded()) {
            throw new LogicException('Entity store not started');
        }
        Sync::registerNamespace($prefix, $uri, $namespace, $helper);

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function getInitialWorkingDirectory(): string
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
    final public function setInitialWorkingDirectory(string $directory)
    {
        $this->WorkingDirectory = $directory;
        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function registerShutdownReport(
        int $level = Console::LEVEL_INFO,
        bool $includeResourceUsage = true,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
    ) {
        self::$ShutdownReportLevel = $level;
        self::$ShutdownReportResourceUsage = $includeResourceUsage;
        self::$ShutdownReportRunningTimers = $includeRunningTimers;
        self::$ShutdownReportMetricGroups = $groups;
        self::$ShutdownReportMetricLimit = $limit;

        if (self::$ShutdownReportIsRegistered) {
            return $this;
        }

        register_shutdown_function(
            static function () {
                self::doReportMetrics(
                    self::$ShutdownReportLevel,
                    self::$ShutdownReportRunningTimers,
                    self::$ShutdownReportMetricGroups,
                    self::$ShutdownReportMetricLimit,
                );
                if (self::$ShutdownReportResourceUsage) {
                    self::doReportResourceUsage(self::$ShutdownReportLevel);
                }
            }
        );

        self::$ShutdownReportIsRegistered = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function reportResourceUsage(int $level = Console::LEVEL_INFO)
    {
        self::doReportResourceUsage($level);
        return $this;
    }

    /**
     * @param Console::LEVEL_* $level
     */
    private static function doReportResourceUsage(int $level): void
    {
        /** @var float */
        $requestTime = $_SERVER['REQUEST_TIME_FLOAT'];
        [$peakMemory, $elapsedTime, $userTime, $systemTime] = [
            memory_get_peak_usage(),
            microtime(true) - $requestTime,
            ...Sys::getCpuUsage(),
        ];

        Console::print(
            "\n" . sprintf(
                'CPU time: **%.3fs** elapsed, **%.3fs** user, **%.3fs** system; memory: **%s** peak',
                $elapsedTime,
                $userTime / 1000000,
                $systemTime / 1000000,
                Format::bytes($peakMemory),
            ),
            $level,
            Console::TYPE_UNFORMATTED,
        );
    }

    /**
     * @inheritDoc
     */
    final public function reportMetrics(
        int $level = Console::LEVEL_INFO,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
    ) {
        self::doReportMetrics($level, $includeRunningTimers, $groups, $limit);
        return $this;
    }

    /**
     * @param Console::LEVEL_* $level
     * @param string[]|string|null $groups
     */
    private static function doReportMetrics(
        int $level,
        bool $includeRunningTimers,
        $groups,
        ?int $limit
    ): void {
        if ($groups !== null) {
            $groups = (array) $groups;
        }
        $groupCounters = Profile::getInstance()->getCounters($groups);
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
                "**{{#}}** {{#:event}} recorded by %s in group '**%s**':",
                Inflect::format($count, '**{{#}}** {{#:counter}}'),
                $group,
            );

            $valueWidth = strlen((string) $maxValue);
            $format = "  %{$valueWidth}d ***%s***";
            foreach ($counters as $name => $value) {
                $lines[] = sprintf($format, $value, $name);
            }

            if ($hidden = $count - count($counters)) {
                $width = $valueWidth + 3;
                $lines[] = sprintf("%{$width}s~~(and %d more)~~", '', $hidden);
            }

            $report[] = implode("\n", $lines);
        }

        $groupTimers = Profile::getInstance()->getTimers($includeRunningTimers, $groups);
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
                "**%.3fms** recorded by **{{#}}** {{#:timer}} in group '**%s**':",
                $totalTime,
                $group,
            );

            $timeWidth = strlen((string) (int) $maxTime) + 4;
            $runsWidth = strlen((string) $maxRuns) + 2;
            $format = "  %{$timeWidth}.3fms ~~{~~%{$runsWidth}s~~}~~ ***%s***";
            foreach ($timers as $name => [$time, $runs]) {
                $lines[] = sprintf($format, $time, sprintf('*%d*', $runs), $name);
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
                Console::TYPE_UNFORMATTED,
            );
        }
    }
}
