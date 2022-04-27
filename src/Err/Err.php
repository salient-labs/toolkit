<?php

declare(strict_types=1);

namespace Lkrms\Err;

use Lkrms\Runtime;
use Whoops\Handler\HandlerInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Just a Whoops wrapper
 *
 * @package Lkrms
 */
abstract class Err
{
    /**
     * @var Run|null
     */
    private static $Whoops;

    /**
     * @param callable|HandlerInterface|null $handler Called before standard
     * handlers.
     * @param string|string[]|null $silenceInPaths One or multiple regex
     * patterns to match files where `E_STRICT`, `E_DEPRECATED` and
     * `E_USER_DEPRECATED` errors will be silenced (e.g. your project's `vendor`
     * directory).
     */
    public static function handleErrors($handler = null, $silenceInPaths = null)
    {
        if (!self::$Whoops)
        {
            self::$Whoops = new Run();

            if (PHP_SAPI == "cli")
            {
                self::$Whoops->pushHandler(new CliHandler());
            }
            else
            {
                /**
                 * @todo Log non-CLI errors via Console too (i.e. extend
                 * PrettyPageHandler)
                 */
                self::$Whoops->pushHandler(new PrettyPageHandler());
            }
        }

        if ($handler)
        {
            self::$Whoops->pushHandler($handler);
        }

        if ($silenceInPaths)
        {
            self::$Whoops->silenceErrorsInPaths($silenceInPaths, E_STRICT | E_DEPRECATED | E_USER_DEPRECATED);
        }

        self::$Whoops->register();
    }

    /**
     * @deprecated Use {@see Runtime::getCaller()} instead
     */
    public static function getCaller(int $depth = 0): string
    {
        return implode("", Runtime::getCaller($depth + 1));
    }
}
