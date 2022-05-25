<?php

declare(strict_types=1);

namespace Lkrms;

use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Contract\ISingular;
use Lkrms\Core\Mixin\TFullyGettable;
use Lkrms\Err\Err;
use Lkrms\Store\Cache;
use Lkrms\Util\Env;
use Lkrms\Util\Test;
use RuntimeException;

/**
 * A [soon-to-be] stackable application state container
 *
 * @property-read string $BasePath
 * @property-read string $CachePath
 * @property-read string $DataPath
 * @property-read string $LogPath
 */
final class App implements IGettable, ISingular
{
    use TFullyGettable;

    /**
     * @var App|null
     */
    private static $Instance;

    /**
     * @var string
     */
    protected $BasePath;

    /**
     * @var string
     */
    protected $CachePath;

    /**
     * @var string
     */
    protected $DataPath;

    /**
     * @var string
     */
    protected $LogPath;

    public static function isLoaded(): bool
    {
        return !is_null(self::$Instance);
    }

    /**
     * @param string|null $basePath
     * @param string|string[]|null $silenceErrorsInPaths
     * @return App
     */
    public static function load(
        string $basePath      = null,
        $silenceErrorsInPaths = null
    ) {
        if (self::$Instance)
        {
            throw new RuntimeException(static::class . " already loaded");
        }

        self::$Instance = new App($basePath, $silenceErrorsInPaths);
        return self::$Instance;
    }

    public static function getInstance()
    {
        self::assertIsLoaded();
        return self::$Instance;
    }

    /**
     * Get the value of an instance property
     *
     * @param string $name
     * @return mixed
     */
    public static function get(string $name)
    {
        self::assertIsLoaded();
        return self::$Instance->__get($name);
    }

    private static function assertIsLoaded(): void
    {
        if (!self::$Instance)
        {
            throw new RuntimeException(static::class . " not loaded");
        }
    }

    private function getPath(string $name, string $default): string
    {
        if ($path = Env::get($name, ""))
        {
            return Test::isAbsolutePath($path)
                ? $path
                : $this->BasePath . "/" . $path;
        }

        return $this->BasePath . "/" . $default;
    }

    private function __construct(
        string $basePath      = null,
        $silenceErrorsInPaths = null
    ) {
        if (is_null($basePath) ||
            !is_dir($basePath) ||
            !($this->BasePath = realpath($basePath)))
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

        $this->CachePath = $this->getPath("app_cache_path", "var/cache");
        $this->DataPath  = $this->getPath("app_data_path", "var/lib");
        $this->LogPath   = $this->getPath("app_log_path", "var/log");

        Err::load($silenceErrorsInPaths);
    }

    public function enableCache(): App
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
}
