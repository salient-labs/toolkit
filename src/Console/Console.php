<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Exception;
use Lkrms\Console\ConsoleTarget\Stream;
use Lkrms\Convert;
use RuntimeException;

/**
 * Functions for console output
 *
 * @package Lkrms
 */
class Console
{
    const BLACK = "\033[30m";

    const RED = "\033[31m";

    const GREEN = "\033[32m";

    const YELLOW = "\033[33m";

    const BLUE = "\033[34m";

    const MAGENTA = "\033[35m";

    const CYAN = "\033[36m";

    const WHITE = "\033[37m";

    const GREY = "\033[90m";

    const BLACK_BG = "\033[40m";

    const RED_BG = "\033[41m";

    const GREEN_BG = "\033[42m";

    const YELLOW_BG = "\033[43m";

    const BLUE_BG = "\033[44m";

    const MAGENTA_BG = "\033[45m";

    const CYAN_BG = "\033[46m";

    const WHITE_BG = "\033[47m";

    const GREY_BG = "\033[100m";

    const BOLD = "\033[1m";

    const DIM = "\033[2m";

    const UNDIM = "\033[22m";

    const RESET = "\033[m\017";

    /**
     * @var int
     */
    private static $GroupDepth = null;

    /**
     * @var array
     */
    private static $Targets = [];

    /**
     * @var array
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

    private static function Write(int $level, string $msg1, ?string $msg2, string $pre, string $clr1, string $clr2, string $clrP = null, Exception $ex = null)
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
            if ($target instanceof Stream && $target->AddColour())
            {
                if (is_null($colourMessage))
                {
                    $clrP          = !is_null($clrP) ? $clrP : self::BOLD . $clr2;
                    $_pre          = $clrP . $pre . ($clrP ? self::RESET : "");
                    $_pre          = str_repeat(" ", $margin) . $_pre;
                    $_msg1         = $clr1 . $msg1 . ($clr1 ? self::RESET : "");
                    $_msg2         = $msg2 ? $clr2 . $msg2 . ($clr2 ? self::RESET : "") : "";
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

        self::Write(ConsoleLevel::NOTICE, $msg1, $msg2, ">>> ", self::BOLD, self::CYAN);
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
     * Print "  - $msg1 $msg2" to STDOUT
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function Debug(string $msg1, string $msg2 = null, Exception $ex = null)
    {
        self::Write(ConsoleLevel::DEBUG, $msg1, $msg2, "  - ", self::DIM . self::BOLD, self::DIM . self::CYAN, null, $ex);
    }

    /**
     * Print " -> $msg1 $msg2" to STDOUT
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function Log(string $msg1, string $msg2 = null, Exception $ex = null)
    {
        self::Write(ConsoleLevel::INFO, $msg1, $msg2, " -> ", "", self::YELLOW, null, $ex);
    }

    /**
     * Print "==> $msg1 $msg2" to STDOUT
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function Info(string $msg1, string $msg2 = null, Exception $ex = null)
    {
        self::Write(ConsoleLevel::NOTICE, $msg1, $msg2, "==> ", self::BOLD, self::CYAN, null, $ex);
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
        self::Write(ConsoleLevel::WARNING, $msg1, $msg2, "  ! ", self::YELLOW . self::BOLD, self::BOLD, self::YELLOW . self::BOLD, $ex);
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
        self::Write(ConsoleLevel::ERROR, $msg1, $msg2, " !! ", self::RED . self::BOLD, self::BOLD, self::RED . self::BOLD, $ex);
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

