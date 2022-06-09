<?php

declare(strict_types=1);

namespace Lkrms\App;

use Lkrms\Container\Container;
use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Mixin\TFullyGettable;
use Lkrms\Err\Err;
use Lkrms\Store\Cache;
use Lkrms\Util\Env;
use Lkrms\Util\Test;
use RuntimeException;

/**
 * A stackable service container with runtime environment helpers
 *
 * Typically accessed via the {@see App} facade.
 *
 * @property-read string $BasePath
 * @property-read string $CachePath
 * @property-read string $DataPath
 * @property-read string $LogPath
 * @property-read string $TempPath
 */
final class AppContainer extends Container implements IGettable
{
    use TFullyGettable;

    /**
     * @internal
     * @var string
     */
    protected $BasePath;

    /**
     * @internal
     * @var string
     */
    protected $CachePath;

    /**
     * @internal
     * @var string
     */
    protected $DataPath;

    /**
     * @internal
     * @var string
     */
    protected $LogPath;

    /**
     * @internal
     * @var string
     */
    protected $TempPath;

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

    public function __construct(
        string $basePath      = null,
        $silenceErrorsInPaths = null
    ) {
        parent::__construct();

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
        $this->TempPath  = $this->getPath("app_temp_path", "var/tmp");

        Err::load($silenceErrorsInPaths);
    }

    public function hasCacheStore(): bool
    {
        return file_exists($this->CachePath . "/cache.db");
    }

    public function enableCache(): AppContainer
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
