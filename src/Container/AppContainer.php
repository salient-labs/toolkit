<?php

declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Concern\TReadable;
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

    private function getPath(string $name, string $default): string
    {
        $path = (($path = Env::get($name, ""))
            ? (Test::isAbsolutePath($path) ? $path : $this->BasePath . "/" . $path)
            : $this->BasePath . "/" . $default);
        File::maybeCreateDirectory($path);
        return $path;
    }

    final protected function _getCachePath(): string
    {
        return $this->_CachePath
            ?: ($this->_CachePath = $this->getPath("app_cache_path", "var/cache"));
    }

    final protected function _getDataPath(): string
    {
        return $this->_DataPath
            ?: ($this->_DataPath = $this->getPath("app_data_path", "var/lib"));
    }

    final protected function _getLogPath(): string
    {
        return $this->_LogPath
            ?: ($this->_LogPath = $this->getPath("app_log_path", "var/log"));
    }

    final protected function _getTempPath(): string
    {
        return $this->_TempPath
            ?: ($this->_TempPath = $this->getPath("app_temp_path", "var/tmp"));
    }

    public static function getReadable(): array
    {
        return ["BasePath"];
    }

    public function __construct(string $basePath = null)
    {
        parent::__construct();

        if (self::hasGlobalContainer() &&
            ($global = get_class(self::getGlobalContainer())) !== Container::class)
        {
            throw new RuntimeException("Global container already loaded: " . $global);
        }

        self::setGlobalContainer($this);

        if (is_null($basePath))
        {
            $basePath = Env::get("app_base_path", "") ?: Composer::getRootPackagePath();
        }

        if (!is_dir($basePath) ||
            ($this->BasePath = realpath($basePath)) === false)
        {
            throw new RuntimeException("Invalid basePath: " . $basePath);
        }

        if (file_exists($env = $this->BasePath . "/.env"))
        {
            Env::loadFile($env);
        }
        else
        {
            Env::apply();
        }

        Console::registerStdioTargets();

        Err::load();
        if ($path = Composer::getPackagePath("adodb/adodb-php"))
        {
            Err::silencePaths($path);
        }
    }

    /**
     * @return $this
     */
    final public function loadCache()
    {
        $cacheDb = $this->CachePath . "/cache.db";

        if (!Cache::isLoaded())
        {
            Cache::load($cacheDb);
        }
        elseif (!Test::areSameFile($cacheDb, $file = Cache::getFilename() ?: ""))
        {
            throw new RuntimeException("Cache database already loaded: $file");
        }

        return $this;
    }

    /**
     * @return $this
     */
    final public function loadCacheIfExists()
    {
        if (file_exists($this->CachePath . "/cache.db"))
        {
            $this->loadCache();
        }

        return $this;
    }

    /**
     * @param string|null $name Defaults to the name used to run the script.
     * @return $this
     */
    final public function logConsoleMessages(?string $name = null, array $levels = ConsoleLevels::ALL_DEBUG)
    {
        $name = ($name
            ? basename($name, ".log")
            : Convert::pathToBasename(Sys::getProgramName(), 1));
        Console::registerTarget(StreamTarget::fromPath($this->LogPath . "/$name.log"), $levels);

        return $this;
    }

}
