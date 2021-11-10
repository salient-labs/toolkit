<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Exception;
use Lkrms\Console\ConsoleTarget\Stream;
use Lkrms\Convert;
use Lkrms\Error;
use RuntimeException;

/**
 * Functions for console output
 *
 * @package Lkrms
 */
class Console
{
    /**
     * @var int
     */
    private static $GroupDepth = null;

    /**
     * @var array<int,ConsoleTarget>
     */
    private static $Targets = [];

    /**
     * @var array<int,ConsoleTarget>
     */
    private static $TtyTargets = [];

    /**
     * @var bool
     */
    private static $TargetsChecked = false;

    /**
     * @var int
     */
    private static $Warnings = 0;

    /**
     * @var int
     */
    private static $Errors = 0;

    private static function CheckGroupDepth()
    {
        if (is_null(self::$GroupDepth))
        {
            self::$GroupDepth = 0;

            return false;
        }

        return true;
    }

    private static function CheckTargets()
    {
        if (self::$TargetsChecked)
        {
            return;
        }

        // If no targets have been added, log everything to /tmp/{basename}-{realpath hash}-{user ID}.log
        if (empty(self::$Targets))
        {
            $path     = realpath($_SERVER["SCRIPT_FILENAME"]);
            $basename = basename($path);
            $hash     = Convert::Hash($path);
            $euid     = posix_geteuid();
            $logFile  = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . "$basename-$hash-$euid.log";
            self::AddTarget(Stream::FromPath($logFile));
        }

        // - errors and warnings -> STDERR
        // - notices and info    -> STDOUT
        self::AddTarget(self::$TtyTargets[2] = new Stream(STDERR, [
            ConsoleLevel::EMERGENCY,
            ConsoleLevel::ALERT,
            ConsoleLevel::CRITICAL,
            ConsoleLevel::ERROR,
            ConsoleLevel::WARNING,
        ]));
        self::AddTarget(self::$TtyTargets[1] = new Stream(STDOUT, [
            ConsoleLevel::NOTICE,
            ConsoleLevel::INFO,
        ]));
        self::$TargetsChecked = true;
    }

    /**
     * Return a ConsoleTarget array with STDOUT and STDERR at keys 1 and 2 respectively
     *
     * @return array<int,ConsoleTarget>
     * @throws RuntimeException
     */
    public static function GetTtyTargets(): array
    {
        self::CheckTargets();

        return self::$TtyTargets;
    }

    public static function AddTarget(ConsoleTarget $target)
    {
        self::$Targets[] = $target;
    }

    private static function Write(
        int $level,
        string $msg1,
        ?string $msg2,
        string $pre,
        string $clr1,
        string $clr2,
        string $clrP  = null,
        Exception $ex = null,
        bool $ttyOnly = false
    )
    {
        //
        self::CheckGroupDepth();
        $margin = self::$GroupDepth * 4;

        //
        $indent = strlen($pre);
        $indent = max(0, strpos($msg1, "\n") ? $indent : $indent - 4);

        if ($margin + $indent)
        {
            $msg1 = str_replace("\n", "\n" . str_repeat(" ", $margin + $indent), $msg1);
        }

        if (!is_null($msg2))
        {
            if (strpos($msg2, "\n"))
            {
                $msg2 = str_replace("\n", "\n" . str_repeat(" ", $margin + $indent + 2), "\n" . ltrim($msg2));
            }
            else
            {
                $msg2 = " " . $msg2;
            }
        }

        $message       = str_repeat(" ", $margin) . $pre . $msg1 . $msg2;
        $colourMessage = null;
        $context       = [];

        if ($ex)
        {
            // As per PSR-3
            $context["exception"] = $ex;
        }

        self::CheckTargets();

        foreach (self::$Targets as $target)
        {
            if ($ttyOnly && !$target->IsTty())
            {
                continue;
            }

            if ($target instanceof Stream && $target->AddColour())
            {
                if (is_null($colourMessage))
                {
                    $clrP          = !is_null($clrP) ? $clrP : ConsoleColour::BOLD . $clr2;
                    $_pre          = $clrP . $pre . ($clrP ? ConsoleColour::RESET : "");
                    $_pre          = str_repeat(" ", $margin) . $_pre;
                    $_msg1         = $clr1 . $msg1 . ($clr1 ? ConsoleColour::RESET : "");
                    $_msg2         = $msg2 ? $clr2 . $msg2 . ($clr2 ? ConsoleColour::RESET : "") : "";
                    $colourMessage = $_pre . $_msg1 . $_msg2;
                }

                $target->Write($colourMessage, $context, $level);
            }
            else
            {
                $target->Write($message, $context, $level);
            }
        }
    }

    /**
     * Increase indent and print "<<< $msg1 $msg2" to STDOUT
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function Group(string $msg1, string $msg2 = null)
    {
        if (self::CheckGroupDepth())
        {
            self::$GroupDepth++;
        }

        self::Write(ConsoleLevel::NOTICE, $msg1, $msg2, ">>> ",
            ConsoleColour::BOLD,
            ConsoleColour::CYAN);
    }

    /**
     * Decrease indent
     *
     */
    public static function GroupEnd()
    {
        self::CheckGroupDepth();
        self::$GroupDepth--;
        self::$GroupDepth = self::$GroupDepth < 0 ? null : self::$GroupDepth;
    }

    /**
     * Print "  - {CALLER} $msg1 $msg2" to STDOUT
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Exception|null $ex
     * @param int $depth Passed to {@link Error::GetCaller()}. To print your
     * caller's name instead of your own, set `$depth` = 2.
     * @return void
     * @throws RuntimeException
     */
    public static function Debug(string $msg1, string $msg2 = null,
        Exception $ex = null, int $depth = 1)
    {
        self::Write(ConsoleLevel::DEBUG,
            (($caller = Error::GetCaller($depth)) ? "{{$caller}} " : "") . $msg1,
            $msg2, "  - ",
            ConsoleColour::DIM . ConsoleColour::BOLD,
            ConsoleColour::DIM . ConsoleColour::CYAN, null, $ex);
    }

    /**
     * Print " -> $msg1 $msg2" to STDOUT
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function Log(string $msg1, string $msg2 = null,
        Exception $ex = null)
    {
        self::Write(ConsoleLevel::INFO, $msg1, $msg2, " -> ", "",
            ConsoleColour::YELLOW, null, $ex);
    }

    /**
     * Print " -> $msg1 $msg2" to TTY targets only
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function LogProgress(string $msg1, string $msg2 = null,
        Exception $ex = null)
    {
        self::Write(ConsoleLevel::INFO, $msg1, $msg2, " -> ", "",
            ConsoleColour::YELLOW, null, $ex, true);
    }

    /**
     * Print "==> $msg1 $msg2" to STDOUT
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function Info(string $msg1, string $msg2 = null, Exception $ex = null)
    {
        self::Write(ConsoleLevel::NOTICE, $msg1, $msg2, "==> ",
            ConsoleColour::BOLD,
            ConsoleColour::CYAN, null, $ex);
    }

    /**
     * Print " :: $msg1 $msg2" to STDERR
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function Warn(string $msg1, string $msg2 = null, Exception $ex = null)
    {
        self::$Warnings++;
        self::Write(ConsoleLevel::WARNING, $msg1, $msg2, "  ! ",
            ConsoleColour::YELLOW . ConsoleColour::BOLD,
            ConsoleColour::BOLD,
            ConsoleColour::YELLOW . ConsoleColour::BOLD, $ex);
    }

    /**
     * Print " !! $msg1 $msg2" to STDERR
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function Error(string $msg1, string $msg2 = null, Exception $ex = null)
    {
        self::$Errors++;
        self::Write(ConsoleLevel::ERROR, $msg1, $msg2, " !! ",
            ConsoleColour::RED . ConsoleColour::BOLD,
            ConsoleColour::BOLD,
            ConsoleColour::RED . ConsoleColour::BOLD, $ex);
    }

    public static function GetWarnings(): int
    {
        return self::$Warnings;
    }

    public static function GetErrors(): int
    {
        return self::$Errors;
    }

    public static function GetSummary($successText = " without errors"): string
    {
        if (self::$Warnings + self::$Errors)
        {
            $summary = " with " . Convert::NumberToNoun(self::$Errors, "error", null, true);

            if (self::$Warnings)
            {
                $summary .= " and " . Convert::NumberToNoun(self::$Warnings, "warning", null, true);
            }

            return $summary;
        }
        else
        {
            return $successText;
        }
    }
}

