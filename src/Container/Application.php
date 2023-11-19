<?php declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevels;
use Lkrms\Console\Target\StreamTarget;
use Lkrms\Container\Container;
use Lkrms\Contract\IApplication;
use Lkrms\Exception\FilesystemErrorException;
use Lkrms\Exception\InvalidEnvironmentException;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Facade\Err;
use Lkrms\Facade\Format;
use Lkrms\Facade\Profile;
use Lkrms\Facade\Sync;
use Lkrms\Facade\Sys;
use Lkrms\Utility\Catalog\EnvFlag;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Lkrms\Utility\File;
use Lkrms\Utility\Package;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Test;
use LogicException;
use Phar;

/**
 * A service container for applications
 */
class Application extends Container implements IApplication
{
    private const DIR_CONFIG = 'CONFIG';
    private const DIR_DATA = 'DATA';
    private const DIR_STATE = 'STATE';

    protected Env $Env;

    private string $AppName;

    private string $BasePath;

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

    /**
     * @var StreamTarget[]
     */
    private array $LogTargets = [];

    /**
     * @var StreamTarget[]
     */
    private array $DebugLogTargets = [];

    /**
     * @var int|float|null
     */
    private static $StartTime;

    /**
     * @var Level::*
     */
    private static int $ShutdownReportLevel;

    /**
     * @var string[]|string|null
     */
    private static $ShutdownReportTimerTypes;

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

        $path = $this->Env->get($name, null);
        if ($path !== null) {
            if (trim($path) === '') {
                throw new InvalidEnvironmentException(
                    sprintf('%s disabled in this environment', $name)
                );
            }
            if (!Test::isAbsolutePath($path)) {
                $path = "{$this->BasePath}/$path";
            }
            return $this->checkPath($path, $name, $create, $save);
        }

        // If running from source, return `"{$this->BasePath}/$sourceChild"` if
        // it resolves to a writable directory
        if (!$this->isProduction()) {
            $path = "{$this->BasePath}/$sourceChild";
            if (Test::firstExistingDirectoryIsWritable($path)) {
                return $this->checkPath($path, $name, $create, $save);
            }
        }

        $app = $this->getAppName();

        if (\PHP_OS_FAMILY === 'Windows') {
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

            return $this->checkPath(
                Arr::implode('/', [$path, $app, $windowsChild]),
                $name,
                $create,
                $save,
            );
        }

        $home = $this->Env->home();
        if ($home === null || !is_dir($home)) {
            throw new InvalidEnvironmentException('Home directory not found');
        }

        switch ($parent) {
            case self::DIR_CONFIG:
                $path = $this->Env->get('XDG_CONFIG_HOME', "{$home}/.config");
                break;

            case self::DIR_DATA:
                $path = $this->Env->get('XDG_DATA_HOME', "{$home}/.local/share");
                break;

            case self::DIR_STATE:
            default:
                $path = $this->Env->get('XDG_CACHE_HOME', "{$home}/.cache");
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
        if (!Test::isAbsolutePath($path)) {
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
     * @inheritDoc
     */
    public function __construct(
        ?string $basePath = null,
        ?string $appName = null,
        int $envFlags = EnvFlag::ALL
    ) {
        if (!isset(self::$StartTime)) {
            self::$StartTime = hrtime(true);
        }

        parent::__construct();

        static::setGlobalContainer($this);

        $this->Env = $this->singletonIf(Env::class)->get(Env::class);

        $this->AppName = $appName
            ?? Pcre::replace(
                // Match `git describe --long` and similar formats
                '/-v?[0-9]+(\.[0-9]+){0,3}(-[0-9]+)?(-g?[0-9a-f]+)?$/i',
                '',
                Sys::getProgramBasename('.php', '.phar')
            );

        $explicitBasePath = true;
        $defaultBasePath = false;
        if ($basePath === null) {
            $explicitBasePath = false;
            $basePath = $this->Env->get('app_base_path', null);
            if ($basePath === null) {
                $defaultBasePath = true;
                $basePath = Package::path();
            }
        }

        $_basePath = $basePath;
        if (!is_dir($basePath) ||
                ($basePath = File::realpath($basePath)) === false) {
            $exception =
                $explicitBasePath || $defaultBasePath
                    ? FilesystemErrorException::class
                    : InvalidEnvironmentException::class;
            throw new $exception(
                sprintf('Invalid basePath: %s', $_basePath)
            );
        }

        $this->BasePath = $basePath;

        $this->RunningFromSource =
            !extension_loaded('Phar') ||
            !Phar::running();

        if ($this->RunningFromSource) {
            $files = [];
            $env = $this->Env->environment();
            if ($env !== null) {
                $files[] = "{$this->BasePath}/.env.{$env}";
            }
            $files[] = "{$this->BasePath}/.env";
            foreach ($files as $file) {
                if (is_file($file)) {
                    $this->Env->load($file);
                    break;
                }
            }
        }

        $this->Env->apply($envFlags);

        Console::registerStdioTargets();

        Err::register();

        $adodb = Package::packagePath('adodb/adodb-php');
        if ($adodb !== null) {
            Err::silencePath($adodb);
        }
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        $this->stopSync()->stopCache();
        unset($this->Env);
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
    final public function registerShutdownReport(
        int $level = Level::INFO,
        $timerTypes = null,
        bool $resourceUsage = true
    ) {
        self::$ShutdownReportLevel = $level;
        self::$ShutdownReportTimerTypes = $timerTypes;
        self::$ShutdownReportResourceUsage = $resourceUsage;

        if (self::$ShutdownReportIsRegistered) {
            return $this;
        }

        register_shutdown_function(
            static function () {
                $level = self::$ShutdownReportLevel;
                self::doReportTimers($level, true, self::$ShutdownReportTimerTypes, 10);
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
        $env = $this->Env->environment();

        return
            $env === 'production' ||
            ($env === null &&
                (!$this->RunningFromSource ||
                    !Package::hasDevPackages()));
    }

    /**
     * @inheritDoc
     */
    final public function getProgramName(): string
    {
        return Sys::getProgramBasename();
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
    final public function env(): Env
    {
        return $this->Env;
    }

    /**
     * @inheritDoc
     */
    final public function startCache()
    {
        $cacheDb = $this->getCacheDb();

        if (Cache::isLoaded()) {
            $file = Cache::getFilename();
            if (Test::areSameFile($cacheDb, $file)) {
                return $this;
            }
            throw new LogicException(sprintf('Cache store already started: %s', $file));
        }

        Cache::load($cacheDb);

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
        if (!Cache::isLoaded() ||
                !Test::areSameFile($this->getCacheDb(false), Cache::getFilename())) {
            return $this;
        }
        Cache::close();
        return $this;
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
            Console::registerTarget($target, ConsoleLevels::ALL_EXCEPT_DEBUG);
            $this->LogTargets[$name] = $target;
        }

        if (($debug || ($debug === null && $this->Env->debug())) &&
                !isset($this->DebugLogTargets[$name])) {
            $target = StreamTarget::fromPath($this->getLogPath() . "/$name.debug.log");
            Console::registerTarget($target, ConsoleLevels::ALL);
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
            $file = Sync::getFilename();
            if (Test::areSameFile($syncDb, $file)) {
                return $this;
            }
            throw new LogicException(sprintf('Entity store already started: %s', $file));
        }

        Sync::load(
            $syncDb,
            $command === null
                ? Sys::getProgramName($this->BasePath)
                : $command,
            ($arguments === null
                ? (\PHP_SAPI == 'cli'
                    ? array_slice($_SERVER['argv'], 1)
                    : ['_GET' => $_GET, '_POST' => $_POST])
                : $arguments)
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function syncNamespace(string $prefix, string $uri, string $namespace, ?string $resolver = null)
    {
        if (!Sync::isLoaded()) {
            throw new LogicException('Entity store not started');
        }
        Sync::namespace($prefix, $uri, $namespace, $resolver);

        return $this;
    }

    /**
     * @inheritDoc
     */
    final public function stopSync()
    {
        if (!Sync::isLoaded() ||
                !Test::areSameFile($this->getSyncDb(false), Sync::getFilename())) {
            return $this;
        }
        Sync::close();
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

        Console::print("\n" . sprintf(
            'CPU time: **%.3fs** elapsed, **%.3fs** user, **%.3fs** system; memory: **%s** peak',
            ($endTime - self::$StartTime) / 1000000000,
            $userTime / 1000000,
            $systemTime / 1000000,
            Format::bytes($peakMemory, 3)
        ), $level);
    }

    /**
     * @inheritDoc
     */
    final public function reportTimers(
        int $level = Level::INFO,
        bool $includeRunning = true,
        $types = null,
        ?int $limit = 10
    ) {
        self::doReportTimers($level, $includeRunning, $types, $limit);
        return $this;
    }

    /**
     * @param Level::* $level
     * @param string[]|string|null $types
     */
    private static function doReportTimers(int $level, bool $includeRunning, $types, ?int $limit): void
    {
        $typeTimers = Profile::getTimers($includeRunning, $types);
        foreach ($typeTimers as $type => $timers) {
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
                array_splice($timers, $limit);
            }

            $lines = [];
            $lines[] = sprintf(
                "Timing: **%.3fms** recorded by **%d** %s with type '**%s**':",
                $totalTime,
                $count,
                Convert::plural($count, 'timer'),
                $type,
            );

            $timeWidth = strlen((string) (int) $maxTime) + 4;
            $runsWidth = strlen((string) (int) $maxRuns) + 2;
            foreach ($timers as $name => [$time, $runs]) {
                $lines[] = sprintf(
                    "  %{$timeWidth}.3fms ~~{~~%{$runsWidth}s~~}~~ ***%s***",
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

        if ($report ?? null) {
            Console::print("\n" . implode("\n\n", $report), $level);
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
