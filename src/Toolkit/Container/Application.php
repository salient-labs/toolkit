<?php declare(strict_types=1);

namespace Salient\Container;

use Salient\Cache\CacheStore;
use Salient\Console\Target\StreamTarget;
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
use Salient\Utility\Exception\ShouldNotHappenException;
use Salient\Utility\Arr;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Format;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Json;
use Salient\Utility\Package;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Salient\Utility\Sys;
use DateTime;
use DateTimeZone;
use LogicException;
use Phar;

/**
 * @api
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
     * [ Path type => [ name, parent type, child, source child ], ... ]
     */
    private const PATHS = [
        self::PATH_CACHE => ['cache', self::PARENT_STATE, 'cache', 'var/cache'],
        self::PATH_CONFIG => ['config', self::PARENT_CONFIG, 'config', 'var/lib/config'],
        self::PATH_DATA => ['data', self::PARENT_DATA, 'data', 'var/lib/data'],
        self::PATH_LOG => ['log', self::PARENT_STATE, 'log', 'var/log'],
        self::PATH_TEMP => ['temp', self::PARENT_STATE, 'tmp', 'var/tmp'],
    ];

    /**
     * [ Parent type => [ variable, default directory, add child?, Windows variable ], ... ]
     */
    private const PARENTS = [
        self::PARENT_CONFIG => ['XDG_CONFIG_HOME', '.config', false, 'APPDATA'],
        self::PARENT_DATA => ['XDG_DATA_HOME', '.local/share', false, 'APPDATA'],
        self::PARENT_STATE => ['XDG_CACHE_HOME', '.cache', true, 'LOCALAPPDATA'],
    ];

    private string $Name;
    private string $BasePath;
    private ?string $PharPath;
    /** @var array<self::PATH_*,string> */
    private array $Path;
    /** @var array<self::PATH_*,bool> */
    private array $PathIsCreated;
    private bool $OutputLogIsRegistered = false;
    private ?int $HarListenerId = null;
    private ?CurlerHarRecorder $HarRecorder = null;
    private string $HarFilename;
    private ?CacheStore $CacheStore = null;
    private ?SyncStore $SyncStore = null;
    private string $InitialWorkingDirectory;

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
     * This container:
     *
     * - makes itself the global container
     * - gets the current environment from {@see Env::getEnvironment()}, checks
     *   for the following files, and loads values into the environment from the
     *   first that exists:
     *   - `<base_path>/.env.<environment>`
     *   - `<base_path>/.env`
     * - applies values from the environment to the running script (if
     *   `$envFlags` is non-zero)
     * - calls {@see Console::registerStderrTarget()} to register `STDERR` for
     *   console output if running on the command line
     * - calls {@see Err::register()} to register error, exception and shutdown
     *   handlers
     * - calls {@see Config::loadDirectory()} to load values from files in
     *   `$configDir` (if it exists and is a directory)
     *
     * @api
     *
     * @param string|null $basePath If `null`, the value of environment variable
     * `app_base_path` is used if present, otherwise the path of the root
     * package is used.
     * @param non-empty-string|null $name If `null`, configuration value
     * `"app.name"` is used if set, otherwise the basename of the file used to
     * run the script is used after removing common PHP file extensions and
     * recognised version numbers.
     * @param int-mask-of<Env::APPLY_*> $envFlags Values to apply from the
     * environment to the running script.
     * @param string|null $configDir An absolute path, a path relative to the
     * application's base path, or `null` if configuration files should not be
     * loaded.
     */
    public function __construct(
        ?string $basePath = null,
        ?string $name = null,
        int $envFlags = Env::APPLY_ALL,
        ?string $configDir = 'config'
    ) {
        parent::__construct();

        self::setGlobalContainer($this);

        $envBasePath = false;
        if ($basePath === null) {
            $envBasePath = true;
            $basePath = Env::get('app_base_path', null);
            if ($basePath === null) {
                $envBasePath = false;
                $basePath = Package::path();
            }
        }
        if (!is_dir($basePath)) {
            $exception = !$envBasePath
                ? FilesystemErrorException::class
                : InvalidEnvironmentException::class;
            throw new $exception(sprintf('Invalid base path: %s', $basePath));
        }
        $this->BasePath = File::realpath($basePath);
        $this->PharPath = extension_loaded('Phar')
            ? Str::coalesce(Phar::running(), null)
            : null;
        $this->InitialWorkingDirectory = File::getcwd();

        if (($env = Env::getEnvironment()) !== null) {
            $files[] = $this->BasePath . '/.env.' . $env;
        }
        $files[] = $this->BasePath . '/.env';
        foreach ($files as $file) {
            if (is_file($file)) {
                Env::loadFiles($file);
                break;
            }
        }
        if ($envFlags) {
            Env::apply($envFlags);
        }

        Console::registerStderrTarget();

        Err::register();
        $adodbPath = Package::getPackagePath('adodb/adodb-php');
        if ($adodbPath !== null) {
            Err::silencePath($adodbPath);
        }

        if ($configDir !== null) {
            if (!File::isAbsolute($configDir)) {
                $configDir = $this->BasePath . '/' . $configDir;
            }
            if (is_dir($configDir)) {
                Config::loadDirectory($configDir);
            }
        }

        $this->Name = $name
            ?? (
                Config::isLoaded()
                && is_string($value = Config::get('app.name', null))
                    ? $value
                    : null
            )
            ?? Regex::replace(
                '/-v?[0-9]+(\.[0-9]+){0,3}(-[0-9]+)?(-g?[0-9a-f]+)?$/iD',
                '',
                Sys::getProgramBasename('.php', '.phar'),
            );
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        $this->stopSync();
        $this->stopCache();
        if ($this->HarListenerId !== null) {
            Event::removeListener($this->HarListenerId);
            $this->HarListenerId = null;
        } elseif ($this->HarRecorder) {
            $this->HarRecorder->close();
            $this->HarRecorder = null;
        }
        parent::unload();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->Name;
    }

    /**
     * @inheritDoc
     */
    public function getVersion(bool $withRef = true): string
    {
        $version = Package::version(true, false);
        return $withRef
            && ($ref = Package::ref()) !== null
            && !Str::startsWith($version, ['dev-' . $ref, $ref])
                ? "$version ($ref)"
                : $version;
    }

    /**
     * @inheritDoc
     */
    public function getVersionString(): string
    {
        return sprintf(
            '%s %s PHP %s',
            $this->Name,
            $this->getVersion(),
            \PHP_VERSION,
        );
    }

    /**
     * @inheritDoc
     */
    public function getBasePath(): string
    {
        return $this->BasePath;
    }

    /**
     * @inheritDoc
     */
    public function getCachePath(bool $create = true): string
    {
        return $this->getPath(self::PATH_CACHE, $create);
    }

    /**
     * @inheritDoc
     */
    public function getConfigPath(bool $create = true): string
    {
        return $this->getPath(self::PATH_CONFIG, $create);
    }

    /**
     * @inheritDoc
     */
    public function getDataPath(bool $create = true): string
    {
        return $this->getPath(self::PATH_DATA, $create);
    }

    /**
     * @inheritDoc
     */
    public function getLogPath(bool $create = true): string
    {
        return $this->getPath(self::PATH_LOG, $create);
    }

    /**
     * @inheritDoc
     */
    public function getTempPath(bool $create = true): string
    {
        return $this->getPath(self::PATH_TEMP, $create);
    }

    /**
     * @param self::PATH_* $type
     */
    private function getPath(int $type, bool $create): string
    {
        $path = $this->Path[$type] ??= $this->doGetPath($type);
        $this->PathIsCreated[$type] ??= false;
        if ($create && !$this->PathIsCreated[$type]) {
            File::createDir($path);
            $this->PathIsCreated[$type] = true;
        }
        return $path;
    }

    /**
     * @param self::PATH_* $type
     */
    private function doGetPath(int $type): string
    {
        [$name, $parentType, $child, $srcChild] = self::PATHS[$type];
        [$var, $defaultDir, $addChild, $winVar] = self::PARENTS[$parentType];
        $pathVar = 'app_' . $name . '_path';
        $path = Env::get($pathVar, null);
        if ($path === '') {
            throw new InvalidEnvironmentException(sprintf(
                'Invalid %s path in environment variable: %s',
                $name,
                $pathVar,
            ));
        }
        if ($path !== null) {
            return File::isAbsolute($path)
                ? $path
                : $this->BasePath . '/' . $path;
        }
        if (!$this->isRunningInProduction()) {
            $path = $this->BasePath . '/' . $srcChild;
            if (File::isCreatable($path)) {
                return $path;
            }
        }
        if (Sys::isWindows()) {
            $path = Arr::implode('/', [Env::get($winVar), $this->Name, $child], '');
        } else {
            $path = Arr::implode('/', [
                Env::get($var, fn() => $this->getHomeDir($defaultDir)),
                $this->Name,
                $addChild ? $child : null,
            ], '');
        }
        if (!File::isAbsolute($path)) {
            throw new InvalidEnvironmentException(
                sprintf('Invalid %s path: %s', $name, $path),
            );
        }
        return $path;
    }

    private function getHomeDir(string $dir): string
    {
        $home = Env::getHomeDir();
        if ($home === null) {
            throw new InvalidEnvironmentException(
                'Home directory not found in environment',
            );
        }
        if (!is_dir($home)) {
            throw new InvalidEnvironmentException(sprintf(
                'Invalid home directory in environment: %s',
                $home,
            ));
        }
        return $home . '/' . $dir;
    }

    /**
     * @inheritDoc
     */
    public function isRunningInProduction(): bool
    {
        return ($env = Env::getEnvironment()) === 'production'
            || ($env === null && (
                $this->PharPath !== null
                || !Package::hasDevPackages()
            ));
    }

    /**
     * @inheritDoc
     */
    public function logOutput(?string $name = null, ?bool $debug = null)
    {
        if ($this->OutputLogIsRegistered) {
            throw new LogicException('Output log already registered');
        }
        $name ??= $this->Name;
        $target = StreamTarget::fromPath($this->getLogPath() . "/$name.log");
        Console::registerTarget($target, Console::LEVELS_ALL_EXCEPT_DEBUG);
        if ($debug ?? Env::getDebug()) {
            $target = StreamTarget::fromPath($this->getLogPath() . "/$name.debug.log");
            Console::registerTarget($target, Console::LEVELS_ALL);
        }
        $this->OutputLogIsRegistered = true;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function exportHar(
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

            $uuid ??= Sync::isLoaded() && Sync::runHasStarted()
                ? Sync::getRunUuid()
                : Get::uuid();
            $filename = sprintf(
                '%s/har/%s-%s-%s.har',
                $this->getLogPath(),
                $name ?? $this->Name,
                (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d-His.v'),
                Get::value($uuid),
            );
            if (file_exists($filename)) {
                throw new ShouldNotHappenException(sprintf(
                    'File already exists: %s',
                    $filename,
                ));
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
     * @inheritDoc
     */
    public function getHarFilename(): ?string
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
    public function startCache()
    {
        if ($this->CacheStore) {
            throw new LogicException('Cache already started');
        }
        if (Cache::isLoaded()) {
            $cache = Cache::getInstance();
            if (
                $cache instanceof CacheStore
                && File::same($this->getCacheDb(false), $file = $cache->getFilename())
            ) {
                return $this;
            }
            throw new LogicException(sprintf(
                'Global cache already started: %s',
                $file ?? get_class($cache),
            ));
        }
        $this->CacheStore = new CacheStore($this->getCacheDb());
        Cache::load($this->CacheStore);
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
    public function resumeCache()
    {
        return file_exists($this->getCacheDb(false))
            ? $this->startCache()
            : $this;
    }

    /**
     * @inheritDoc
     */
    public function stopCache()
    {
        if ($this->CacheStore) {
            $this->CacheStore->close();
            $this->CacheStore = null;
        }
        return $this;
    }

    private function getCacheDb(bool $create = true): string
    {
        return $this->getCachePath($create) . '/cache.db';
    }

    /**
     * @param string[]|null $arguments
     */
    public function startSync(?string $command = null, ?array $arguments = null)
    {
        if ($this->SyncStore) {
            throw new LogicException('Sync entity store already started');
        }
        if (Sync::isLoaded()) {
            $store = Sync::getInstance();
            if (
                $store instanceof SyncStore
                && File::same($this->getSyncDb(false), $file = $store->getFilename())
            ) {
                return $this;
            }
            throw new LogicException(sprintf(
                'Global sync entity store already started: %s',
                $file ?? get_class($store),
            ));
        }
        if ($arguments === null && $command === null) {
            if (\PHP_SAPI === 'cli') {
                /** @var string[] */
                $arguments = $_SERVER['argv'];
                $arguments = array_slice($arguments, 1);
            } else {
                $arguments = [Json::encode([
                    '_GET' => $_GET,
                    '_POST' => $_POST,
                ])];
            }
        }
        $this->SyncStore = new SyncStore(
            $this->getSyncDb(),
            $command ?? Sys::getProgramName($this->BasePath),
            $arguments ?? [],
        );
        Sync::load($this->SyncStore);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function stopSync()
    {
        if ($this->SyncStore) {
            $this->SyncStore->close();
            $this->SyncStore = null;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function sync(
        string $prefix,
        string $uri,
        string $namespace,
        ?SyncNamespaceHelperInterface $helper = null
    ) {
        if (!$this->SyncStore) {
            $this->startSync();
        }
        Sync::registerNamespace($prefix, $uri, $namespace, $helper);
        return $this;
    }

    private function getSyncDb(bool $create = true): string
    {
        return $this->getDataPath($create) . '/sync.db';
    }

    /**
     * @inheritDoc
     */
    public function getInitialWorkingDirectory(): string
    {
        return $this->InitialWorkingDirectory;
    }

    /**
     * @inheritDoc
     */
    public function setInitialWorkingDirectory(string $directory)
    {
        $this->InitialWorkingDirectory = $directory;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function restoreWorkingDirectory()
    {
        if (File::getcwd() !== $this->InitialWorkingDirectory) {
            File::chdir($this->InitialWorkingDirectory);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function reportVersion(int $level = Console::LEVEL_INFO)
    {
        Console::print($this->getVersionString(), $level);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function reportResourceUsage(int $level = Console::LEVEL_INFO)
    {
        self::doReportResourceUsage($level);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function reportMetrics(
        int $level = Console::LEVEL_INFO,
        bool $includeRunningTimers = true,
        $groups = null,
        ?int $limit = 10
    ) {
        self::doReportMetrics($level, $includeRunningTimers, $groups, $limit);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerShutdownReport(
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

        register_shutdown_function(static function () {
            self::doReportMetrics(
                self::$ShutdownReportLevel,
                self::$ShutdownReportRunningTimers,
                self::$ShutdownReportMetricGroups,
                self::$ShutdownReportMetricLimit,
            );
            if (self::$ShutdownReportResourceUsage) {
                self::doReportResourceUsage(self::$ShutdownReportLevel);
            }
        });
        self::$ShutdownReportIsRegistered = true;
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

        Console::print("\n" . sprintf(
            'CPU time: **%.3fs** elapsed, **%.3fs** user, **%.3fs** system; memory: **%s** peak',
            $elapsedTime,
            $userTime / 1000000,
            $systemTime / 1000000,
            Format::bytes($peakMemory),
        ), $level);
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
            Console::print("\n" . implode("\n\n", $report), $level);
        }
    }
}
