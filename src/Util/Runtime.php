<?php

declare(strict_types=1);

namespace Lkrms\Util;

use Lkrms\Concept\Utility;
use Lkrms\Util\Convert;

/**
 * Get information about the runtime environment
 *
 */
final class Runtime extends Utility
{
    /**
     * Use debug_backtrace to get information about the (caller's) caller
     *
     * Returns an associative array with zero or more of the following values,
     * sorted correctly for concatenation to a caller string. Separators are
     * added under integer keys if required.
     * - `class`
     * - `namespace` (if the caller is a namespaced global function)
     * - `file`
     * - `function`
     * - `line`
     *
     * The return values below, for example, would implode to:
     * - `Lkrms\Tests\Runtime\GetCallerClass->getCallerViaMethod:18`
     * - `/path/to/tests/Runtime/GetCallerFile.php::{closure}:38`
     *
     * ```
     * Array
     * (
     *     [class] => Lkrms\Tests\Runtime\GetCallerClass
     *     [0] => ->
     *     [function] => getCallerViaMethod
     *     [1] => :
     *     [line] => 18
     * )
     *
     * Array
     * (
     *     [file] => /path/to/tests/Runtime/GetCallerFile.php
     *     [0] => ::
     *     [function] => {closure}
     *     [1] => :
     *     [line] => 38
     * )
     * ```
     *
     * For information about an earlier frame in the call stack, set `$depth` to
     * `1` or higher.
     *
     * @param int $depth
     * @return array
     */
    public static function getCaller(int $depth = 0): array
    {
        // 0. called us (function = getCaller)
        // 1. called them (function = ourCaller)
        // 2. used the name of their caller (function = callsOurCaller)
        //
        // Use class (or namespace) and function from 2 if possible, otherwise
        // file and line from 1
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 3);
        $file   = $frames[$depth + 1]["file"] ?? null;
        $line   = $frames[$depth + 1]["line"] ?? null;

        if (($frame = $frames[$depth + 2] ?? null) &&
            preg_match('/^(?P<namespace>.*?)(?P<function>[^\\\\]+|\{closure\})$/',
                $frame["function"],
                $function))
        {
            $class     = $frame["class"] ?? null;
            $namespace = $class ? null : $function["namespace"] ?? null;
            $file      = $class || $namespace ? null : $file;

            return array_filter([
                "class"     => $class,
                "namespace" => $namespace,
                "file"      => $file,
                0           => $frame["type"] ?? ($file ? "::" : null),
                "function"  => $function["function"],
                1           => is_null($line) ? null : ":",
                "line"      => $line,
            ]);
        }
        elseif ($frames[$depth + 1] ?? null)
        {
            return array_filter([
                "file" => $file,
                0      => is_null($line) ? null : ":",
                "line" => $line,
            ]);
        }

        return [];
    }

    public static function getMemoryLimit(): int
    {
        return Convert::sizeToBytes(ini_get("memory_limit") ?: "0");
    }

    public static function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    public static function getMemoryUsagePercent(): int
    {
        $limit = self::getMemoryLimit();
        if ($limit <= 0)
        {
            return 0;
        }
        else
        {
            return (int)round(memory_get_usage(true) * 100 / $limit);
        }
    }
}
