<?php

declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Concern\TReadable;
use Lkrms\Console\Console;
use Lkrms\Console\ConsoleLevels;
use Lkrms\Console\ConsoleTarget\StreamTarget;
use Lkrms\Container\Container;
use Lkrms\Contract\IReadable;
use Lkrms\Err\Err;
use Lkrms\Facade\Cache;
use Lkrms\Util\Composer;
use Lkrms\Util\Env;
use Lkrms\Util\File;
use Lkrms\Util\Test;
use RuntimeException;

/**
 * A stackable service container for self-contained applications
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
     * @internal
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

    protected function _getCachePath(): string
    {
        return $this->_CachePath
            ?: ($this->_CachePath = $this->getPath("app_cache_path", "var/cache"));
    }

    protected function _getDataPath(): string
    {
        return $this->_DataPath
            ?: ($this->_DataPath = $this->getPath("app_data_path", "var/lib"));
    }

    protected function _getLogPath(): string
    {
        return $this->_LogPath
            ?: ($this->_LogPath = $this->getPath("app_log_path", "var/log"));
    }

    protected function _getTempPath(): string
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

        if (is_null($basePath))
        {
            $basePath = Composer::getRootPackagePath();
        }

        if (!is_dir($basePath) ||
            ($this->BasePath = realpath($basePath)) === false)
        {
            throw new RuntimeException("Invalid basePath: " . $basePath);
        }

        if (file_exists($env = $this->BasePath . "/.env"))
        {
            Env::load($env);
        }
        else
        {
            Env::apply();
        }

        Console::registerOutputStreams();

        Err::load();
        if ($path = Composer::getPackagePath("adodb/adodb-php"))
        {
            Err::silencePaths($path);
        }
    }

    /**
     * @return $this
     */
    public function enableCache()
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
    public function enableExistingCache()
    {
        if (file_exists($this->CachePath . "/cache.db"))
        {
            $this->enableCache();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function enableMessageLog(string $name = "app", array $levels = ConsoleLevels::ALL_DEBUG)
    {
        $name = basename($name, ".log") ?: "app";
        Console::registerTarget(StreamTarget::fromPath($this->LogPath . "/$name.log", $levels));

        return $this;
    }
}
