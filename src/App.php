<?php

declare(strict_types=1);

namespace Lkrms;

use Lkrms\Core\Contract\IGettable;
use Lkrms\Core\Contract\ISingular;
use Lkrms\Core\Mixin\TGettable;
use Lkrms\Err\Err;
use Lkrms\Util\Env;
use Lkrms\Util\Test;
use RuntimeException;

/**
 * Bootstrap your app
 *
 * @property-read string $BasePath
 * @property-read string $CachePath
 * @property-read string $DataPath
 * @property-read string $LogPath
 *
 * @method static mixed get(string $name)
 */
final class App implements IGettable, ISingular
{
    use TGettable;

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

    public static function getGettable(): array
    {
        return ["*"];
    }

    public static function isLoaded(): bool
    {
        return !is_null(self::$Instance);
    }

    /**
     * @param string|null $basePath
     * @param string|string[]|null $silenceErrorsInPaths
     * @return static
     */
    public static function load(
        string $basePath      = null,
        $silenceErrorsInPaths = null
    ) {
        if (self::$Instance)
        {
            throw new RuntimeException("App already loaded");
        }

        self::$Instance = new App($basePath, $silenceErrorsInPaths);
        return self::$Instance;
    }

    public static function getInstance()
    {
        self::assertIsLoaded();
        return self::$Instance;
    }

    public static function __callStatic(string $name, array $arguments)
    {
        self::assertIsLoaded();

        switch ($name)
        {
            case "get":
                return self::$Instance->__get(...$arguments);

            default:
                return self::$Instance->$name(...$arguments);
        }
    }

    private static function assertIsLoaded(): void
    {
        if (!self::$Instance)
        {
            throw new RuntimeException("App not loaded");
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

        Err::handleErrors($silenceErrorsInPaths);
    }
}
