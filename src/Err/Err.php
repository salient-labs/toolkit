<?php

declare(strict_types=1);

namespace Lkrms\Err;

use Whoops\Handler\HandlerInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Just a Whoops wrapper
 *
 * @package Lkrms
 */
class Err
{
    /**
     * @var Run
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
    public static function HandleErrors($handler = null, $silenceInPaths = null)
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

    public static function GetCaller(int $depth = 0): string
    {
        // 0. called us (function = GetCaller)
        // 1. called them (function = OurCaller)
        // 2. used the name of their caller (function = CallsOurCaller)
        //
        // Use class and function from 2 if possible, otherwise file and line from 1
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 3);

        if ($f = $frames[$depth + 2] ?? null)
        {
            $line = $frames[$depth + 1]["line"] ?? null;

            return implode($f["type"] ?? "", array_filter([
                $f["class"] ?? null,
                preg_replace('/.*\\\\(\{closure\})$/', '$1', $f["function"] ?? ""),
            ])) . ($line ? ":$line" : "");
        }
        elseif ($f = $frames[$depth + 1] ?? null)
        {
            return implode(":", array_filter([
                $f["file"] ?? null,
                $f["line"] ?? null
            ]));
        }

        return "";
    }
}

