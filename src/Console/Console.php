<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Exception;
use Lkrms\Console\ConsoleTarget\NullTarget;
use Lkrms\Console\ConsoleTarget\Stream;
use Lkrms\Convert;
use Lkrms\Env;
use Lkrms\Err;
use Lkrms\File;
use RuntimeException;

/**
 * Print aesthetically pleasing messages to various targets
 *
 * @package Lkrms\Console
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
    private static $LogTargets = [];

    /**
     * @var array<int,ConsoleTarget>
     */
    private static $OutputTargets = [];

    /**
     * @var array<int,ConsoleTarget>
     */
    private static $Targets = [];

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

    public static function Format(string $string, $colour = false): string
    {
        if ($colour)
        {
            list ($bold, $cyan, $yellow, $reset) = [
                ConsoleColour::BOLD,
                ConsoleColour::CYAN,
                ConsoleColour::YELLOW,
                ConsoleColour::RESET
            ];
        }
        else
        {
            list ($bold, $cyan, $yellow, $reset) = [
                "", "", "", ""
            ];
        }

        return preg_replace(
            [
                "/\\b___([^\n]+?)___\\b/", "/\\*\\*\\*([^\n]+?)\\*\\*\\*/",
                "/\\b__([^\n]+?)__\\b/", "/\\*\\*([^\n]+?)\\*\\*/",
                "/\\b_([^\n]+?)_\\b/", "/\\*([^\n]+?)\\*/",
            ], [
                "$bold$cyan\$1$reset", "$bold$cyan\$1$reset",
                "$bold\$1$reset", "$bold\$1$reset",
                "$yellow\$1$reset", "$yellow\$1$reset",
            ],
            $string
        );
    }

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

        // If no output log has been added, create one at
        // /tmp/{basename}-{realpath hash}-{user ID}.log
        if (empty(self::$LogTargets))
        {
            self::AddTarget(Stream::FromPath(File::StablePath(".log")));
        }

        // If no output streams have been added, send errors and warnings to
        // STDERR, and everything else to STDOUT
        if (empty(self::$OutputTargets))
        {
            self::AddTarget(new Stream(STDERR, [
                ConsoleLevel::EMERGENCY,
                ConsoleLevel::ALERT,
                ConsoleLevel::CRITICAL,
                ConsoleLevel::ERROR,
                ConsoleLevel::WARNING,
            ]));

            $levels = [
                ConsoleLevel::NOTICE,
                ConsoleLevel::INFO,
            ];

            if (Env::GetDebug())
            {
                $levels[] = ConsoleLevel::DEBUG;
            }

            self::AddTarget(new Stream(STDOUT, $levels));
        }

        self::$TargetsChecked = true;
    }

    /**
     * Get active targets backed by STDOUT or STDERR
     *
     * @return array<int,ConsoleTarget>
     * @throws RuntimeException
     */
    public static function GetOutputTargets(): array
    {
        self::CheckTargets();

        return self::$OutputTargets;
    }

    public static function AddTarget(ConsoleTarget $target)
    {
        if ($target->IsStdout() || $target->IsStderr())
        {
            self::$OutputTargets[] = $target;
        }
        else
        {
            self::$LogTargets[] = $target;
        }

        if (!$target instanceof NullTarget)
        {
            self::$Targets[] = $target;
        }
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

        $msg = str_repeat(" ", $margin) . $pre . $msg1 . $msg2;

        $clrP   = !is_null($clrP) ? $clrP : ConsoleColour::BOLD . $clr2;
        $ttyMsg = (str_repeat(" ", $margin)
            . $clrP . $pre . ($clrP ? ConsoleColour::RESET : "")
            . $clr1 . $msg1 . ($clr1 ? ConsoleColour::RESET : "")
            . ($msg2 ? $clr2 . $msg2 . ($clr2 ? ConsoleColour::RESET : "") : ""));

        $context = [];

        if ($ex)
        {
            // As per PSR-3
            $context["exception"] = $ex;
        }

        self::Print($msg, $ttyMsg, $level, $context, $ttyOnly);
    }

    public static function Print(
        string $plain,
        string $tty    = null,
        int $level     = ConsoleLevel::INFO,
        array $context = [],
        bool $ttyOnly  = false
    )
    {
        $tty   = self::Format(is_null($tty) ? $plain : $tty, true);
        $plain = self::Format($plain);

        self::CheckTargets();

        foreach (self::$Targets as $target)
        {
            if ($ttyOnly && !$target->IsTty())
            {
                continue;
            }

            if ($target instanceof Stream && $target->AddColour())
            {
                $target->Write($tty, $context, $level);
            }
            else
            {
                $target->Write($plain, $context, $level);
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
     * @param int $depth Passed to {@see Err::GetCaller()}. To print your
     * caller's name instead of your own, set `$depth` = 2.
     * @return void
     * @throws RuntimeException
     */
    public static function Debug(string $msg1, string $msg2 = null,
        Exception $ex = null, int $depth = 0)
    {
        $caller = Err::GetCaller($depth);
        self::Write(ConsoleLevel::DEBUG,
            ($caller ? "{{$caller}} " : "") . $msg1, $msg2, "  - ",
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
        self::Write(ConsoleLevel::INFO, $msg1, $msg2, " -> ",
            "", ConsoleColour::YELLOW, null, $ex);
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
        self::Write(ConsoleLevel::INFO, $msg1, $msg2, " -> ",
            "", ConsoleColour::YELLOW, null, $ex, true);
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

    public static function GetSummary($successText = " without errors", bool $reset = true): string
    {
        if (self::$Warnings + self::$Errors)
        {
            $summary = " with " . Convert::NumberToNoun(self::$Errors, "error", null, true);

            if (self::$Warnings)
            {
                $summary .= " and " . Convert::NumberToNoun(self::$Warnings, "warning", null, true);
            }

            if ($reset)
            {
                self::$Warnings = self::$Errors = 0;
            }

            return $summary;
        }
        else
        {
            return $successText;
        }
    }
}

