<?php declare(strict_types=1);

namespace Lkrms\Err;

use Lkrms\Contract\IFacade;
use Lkrms\Facade\File;
use RuntimeException;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * A static interface to an instance of Whoops\Run
 *
 */
final class Err implements IFacade
{
    /**
     * @var Run|null
     */
    private static $Whoops;

    /**
     * @var bool|null
     */
    private static $WhoopsIsRegistered;

    public static function isLoaded(): bool
    {
        return self::$Whoops && self::$WhoopsIsRegistered;
    }

    /**
     * Register Whoops as the error and exception handler for PHP
     *
     * @return Run
     */
    public static function load()
    {
        if (self::$Whoops && self::$WhoopsIsRegistered) {
            throw new RuntimeException(static::class . ' already loaded');
        } elseif (!self::$Whoops) {
            self::$Whoops = new Run();
            if (PHP_SAPI === 'cli') {
                self::$Whoops->pushHandler(new CliHandler());
                self::$Whoops->sendExitCode(15);
            } else {
                self::$Whoops->pushHandler(new PrettyPageHandler());
            }
        }
        self::$Whoops->register();
        self::$WhoopsIsRegistered = true;

        return self::$Whoops;
    }

    public static function unload(): void
    {
        if (self::$Whoops && self::$WhoopsIsRegistered) {
            self::$Whoops->unregister();
            self::$WhoopsIsRegistered = false;
        }
    }

    /**
     * @return Run
     */
    public static function getInstance()
    {
        if (self::$Whoops && self::$WhoopsIsRegistered) {
            return self::$Whoops;
        }

        return self::load();
    }

    /**
     * @param ...$paths One or more paths where `E_STRICT`, `E_DEPRECATED` and
     * `E_USER_DEPRECATED` errors will be silenced (e.g. your project's `vendor`
     * directory).
     */
    public static function silencePaths(string ...$paths): void
    {
        self::assertIsLoaded();
        $levels = E_STRICT | E_DEPRECATED | E_USER_DEPRECATED;
        foreach ($paths as $path) {
            if (($path = File::realpath($path)) === false) {
                continue;
            }
            $pattern = '/^' . preg_quote($path . '/', '/') . '/';
            if (in_array(
                ['pattern' => $pattern, 'levels' => $levels],
                self::$Whoops->getSilenceErrorsInPaths()
            )) {
                continue;
            }
            self::$Whoops->silenceErrorsInPaths($pattern, $levels);
        }
    }

    public static function __callStatic(string $name, array $arguments)
    {
        self::assertIsLoaded();

        return self::$Whoops->$name(...$arguments);
    }

    private static function assertIsLoaded(): void
    {
        if (!self::$Whoops || !self::$WhoopsIsRegistered) {
            throw new RuntimeException(static::class . ' not loaded');
        }
    }

    private function __construct() {}
}
