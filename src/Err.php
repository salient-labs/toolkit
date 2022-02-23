<?php

declare(strict_types=1);

namespace Lkrms;

use InvalidArgumentException;
use Whoops\Handler\HandlerInterface;
use Whoops\Handler\PlainTextHandler;
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
     * @param callable|HandlerInterface $handler
     * @param string|string[] $silenceInPaths One or multiple regex patterns to
     * match files where `E_STRICT`, `E_DEPRECATED` and `E_USER_DEPRECATED`
     * errors will be silenced (e.g. your project's `vendor` directory).
     * @return Run
     * @throws InvalidArgumentException
     */
    public static function HandleErrors($handler = null, $silenceInPaths = null): Run
    {
        if (!self::$Whoops)
        {
            self::$Whoops = new Run();
        }

        if ($handler)
        {
            self::$Whoops->pushHandler($handler);
        }

        if (empty(self::$Whoops->getHandlers($handler)))
        {
            if (PHP_SAPI == "cli")
            {
                self::$Whoops->pushHandler(new PlainTextHandler());
            }
            else
            {
                self::$Whoops->pushHandler(new PrettyPageHandler());
            }
        }

        if ($silenceInPaths)
        {
            self::$Whoops->silenceErrorsInPaths($silenceInPaths, E_STRICT | E_DEPRECATED | E_USER_DEPRECATED);
        }

        self::$Whoops->register();

        return self::$Whoops;
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

