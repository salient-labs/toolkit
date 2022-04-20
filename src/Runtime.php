<?php

declare(strict_types=1);

namespace Lkrms;

/**
 * Runtime state tracking
 *
 * @package Lkrms
 */
abstract class Runtime
{
    public const RUNNING = 0;

    public const STOPPING = 1;

    private static $State;

    /**
     * Set the state of the running script
     *
     * @param int $state One of the {@see Runtime} state values
     */
    public static function setState(int $state)
    {
        self::$State = $state;
    }

    /**
     * Return true if the script state is STOPPING
     *
     * @return bool
     */
    public static function isStopping(): bool
    {
        return self::$State === self::STOPPING;
    }

    /**
     * Use debug_backtrace to get information about the (caller's) caller
     *
     * Returns an associative array with zero or more of the following values,
     * sorted correctly for concatenation to a caller string. Separators are
     * added under integer keys if required.
     * - `class`
     * - `function`
     * - `file`
     * - `line`
     *
     * The return values below, for example, would implode to:
     * - `Lkrms\Tests\RuntimeTest::returnMethodCaller:23`
     * - `/path/to/tests/RuntimeTest.php{closure}:32`
     *
     * ```
     * Array
     * (
     *     [class] => Lkrms\Tests\TestClass
     *     [0] => ::
     *     [function] => returnMethodCaller
     *     [1] => :
     *     [line] => 23
     * )
     *
     * Array
     * (
     *     [file] => /path/to/tests/src/Runtime.php
     *     [function] => {closure}
     *     [1] => :
     *     [line] => 32
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
        // Use class and function from 2 if possible, otherwise file and line
        // from 1
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 3);
        $file   = $frames[$depth + 1]["file"] ?? null;
        $line   = $frames[$depth + 1]["line"] ?? null;

        if ($frame = $frames[$depth + 2] ?? null)
        {
            $isClosure = (bool)preg_match('/\{closure\}$/', $frame["function"] ?? "");

            return array_filter([
                "class"    => $frame["class"] ?? null,
                0          => $frame["type"] ?? null,
                "file"     => !($frame["class"] ?? null) ? $file : null,
                "function" => $isClosure ? "{closure}" : ($frame["function"] ?? null),
                1          => is_null($line) ? null : ":",
                "line"     => $line,
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

}

