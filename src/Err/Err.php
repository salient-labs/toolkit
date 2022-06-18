<?php

declare(strict_types=1);

namespace Lkrms\Err;

use Lkrms\Contract\IFacade;
use RuntimeException;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * A facade for Whoops
 *
 */
final class Err implements IFacade
{
    /**
     * @var Run|null
     */
    private static $Whoops;

    public static function isLoaded(): bool
    {
        return !is_null(self::$Whoops);
    }

    /**
     * Create the underlying Whoops\Run instance and register it as the error
     * and exception handler for PHP
     *
     * @param string|string[]|null $silenceErrorsInPaths
     * @return Run
     * @see Err::silenceErrorsInPaths()
     */
    public static function load($silenceErrorsInPaths = null)
    {
        if (self::$Whoops)
        {
            throw new RuntimeException(static::class . " already loaded");
        }

        self::$Whoops = new Run();

        if (PHP_SAPI == "cli")
        {
            self::$Whoops->pushHandler(new CliHandler());
        }
        else
        {
            /**
             * @todo Extend PrettyPageHandler
             */
            self::$Whoops->pushHandler(new PrettyPageHandler());
        }

        if ($silenceErrorsInPaths)
        {
            self::silenceErrorsInPaths($silenceErrorsInPaths);
        }

        self::$Whoops->register();
        return self::$Whoops;
    }

    /**
     * @return Run|null
     */
    public static function getInstance()
    {
        self::assertIsLoaded();
        return self::$Whoops;
    }

    /**
     * @param string|string[]|null $patterns One or multiple regex patterns for
     * files where `E_STRICT`, `E_DEPRECATED` and `E_USER_DEPRECATED` errors
     * will be silenced (e.g. your project's `vendor` directory).
     * @return void
     */
    public static function silenceErrorsInPaths($patterns): void
    {
        self::assertIsLoaded();
        self::$Whoops->silenceErrorsInPaths($patterns,
            E_STRICT | E_DEPRECATED | E_USER_DEPRECATED);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        self::assertIsLoaded();
        return self::$Whoops->$name(...$arguments);
    }

    private static function assertIsLoaded(): void
    {
        if (!self::$Whoops)
        {
            throw new RuntimeException(static::class . " not loaded");
        }
    }

    private function __construct()
    {
    }

    /**
     * Similar to load(), but can be called after the underlying Whoops\Run
     * instance has been registered
     *
     * @param string|string[]|null $silenceInPaths
     * @return Run
     * @see Err::load()
     * @see Err::silenceErrorsInPaths()
     */
    public static function handleErrors($silenceInPaths = null)
    {
        if (!self::$Whoops)
        {
            return self::load($silenceInPaths);
        }

        if ($silenceInPaths)
        {
            self::silenceErrorsInPaths($silenceInPaths);
        }

        return self::$Whoops;
    }

}
