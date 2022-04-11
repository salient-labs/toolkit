<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\ConsoleTarget\Stream;
use Lkrms\Convert;
use Lkrms\Env;
use Lkrms\Err;
use Lkrms\File;
use Lkrms\Generate;
use RuntimeException;
use Throwable;

/**
 * Log various message types to various targets
 *
 * @package Lkrms
 */
class Console
{
    /**
     * @var int
     */
    private static $GroupDepth;

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
     * @var array<string,int>
     */
    private static $LoggedOnce = [];

    /**
     * @var int
     */
    private static $Warnings = 0;

    /**
     * @var int
     */
    private static $Errors = 0;

    /**
     * @var bool
     */
    private static $AutomaticLogTarget = true;

    /**
     * Disable the default output log
     *
     * Call while bootstrapping your app to disable the output log created at
     * `<temp_dir>/<basename>-<realpath_hash>-<user_id>.log` by default.
     *
     * This happens automatically when a log target is added explicitly via
     * {@see Console::addTarget()}.
     *
     * @return void
     * @see File::stablePath()
     */
    public static function disableDefaultLogTarget(): void
    {
        if (self::$TargetsChecked && self::$AutomaticLogTarget)
        {
            throw new RuntimeException("Targets have already been created");
        }

        self::$AutomaticLogTarget = false;
    }

    /**
     * Apply inline formatting to a string
     *
     * The following markup is recognised:
     *
     * - `___`Priority 1`___` or `***`Priority 1`***` (bold + colour)
     * - `__`Priority 2`__` or `**`Priority 2`**` (bold)
     * - `_`Priority 3`_` or `*`Priority 3`*` (alternate colour)
     * - `,,`Low Priority`,,` (dim)
     * - `__[[__`SKIP`__]]__` (no formatting)
     *
     * If `$colour` is `true`, inline markup is replaced with terminal escape
     * sequences to set and reset appropriate display attributes. Otherwise, the
     * markup is simply removed.
     *
     * @param string $string
     * @param bool $colour
     * @return string
     */
    public static function format(string $string, $colour = false): string
    {
        if ($colour)
        {
            list ($bold, $unbold, $dim, $undim, $cyan, $yellow, $default) = [
                ConsoleColour::BOLD,
                ConsoleColour::UNBOLD,
                ConsoleColour::DIM,
                ConsoleColour::UNDIM,
                ConsoleColour::CYAN,
                ConsoleColour::YELLOW,
                ConsoleColour::DEFAULT
            ];
        }
        else
        {
            list ($bold, $unbold, $dim, $undim, $cyan, $yellow, $default) = [
                "", "", "", "", "", "", ""
            ];
        }

        $str     = "";
        $matches = [];

        while ($string && preg_match("/^((.*?)__\\[\\[__(.*?)__\\]\\]__)?(.*)\$/s", $string, $matches))
        {
            $format   = $matches[2];
            $noFormat = $matches[3];
            $string   = $matches[4];

            if (!$matches[1])
            {
                $format   = $matches[4];
                $noFormat = "";
                $string   = "";
            }

            $str .= preg_replace([
                "/\\b___([^\n]+?)___\\b/",
                "/\\b__([^\n]+?)__\\b/",
                "/\\b_([^\n]+?)_\\b/",
                "/\\*\\*\\*([^\n]+?)\\*\\*\\*/",
                "/\\*\\*([^\n]+?)\\*\\*/",
                "/\\*([^\n]+?)\\*/",
                "/,,([^\n]+?),,/",
            ], [
                "$bold$cyan\$1$default$unbold",
                "$bold\$1$unbold",
                "$yellow\$1$default",
                "$bold$cyan\$1$default$unbold",
                "$bold\$1$unbold",
                "$yellow\$1$default",
                "$dim\$1$undim"
            ], $format) . $noFormat;
        }

        return $str;
    }

    private static function hasBold(string $string): bool
    {
        $string = preg_replace("/__\\[\\[__(.*?)__\\]\\]__/", "", $string);

        return (bool)preg_match("/(\\b(___?)([^\n]+?)\\2\\b|(\\*\\*\\*?)([^\n]+?)\\4)/", $string);
    }

    private static function checkGroupDepth(): ?int
    {
        // Return null if this is the first call to an output function
        $return           = self::$GroupDepth;
        self::$GroupDepth = self::$GroupDepth ?: 0;

        return $return;
    }

    private static function checkTargets()
    {
        if (self::$TargetsChecked)
        {
            return;
        }

        // If no output log has been added, log everything to
        // `<temp_dir>/<basename>-<realpath_hash>-<user_id>.log`
        if (self::$AutomaticLogTarget && empty(self::$LogTargets))
        {
            self::addTarget(Stream::fromPath(File::getStablePath(".log")));
        }

        // If no output streams have been added and we're running on the command
        // line, send errors and warnings to STDERR, everything else to STDOUT
        if (PHP_SAPI == "cli" && empty(self::$OutputTargets))
        {
            self::addTarget(new Stream(STDERR, [
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

            if (Env::debug())
            {
                $levels[] = ConsoleLevel::DEBUG;
            }

            self::addTarget(new Stream(STDOUT, $levels));
        }

        self::$TargetsChecked = true;
    }

    /**
     * Get active targets backed by STDOUT or STDERR
     *
     * @return array<int,ConsoleTarget>
     * @throws RuntimeException
     */
    public static function getOutputTargets(): array
    {
        self::checkTargets();

        return self::$OutputTargets;
    }

    public static function addTarget(ConsoleTarget $target)
    {
        if ($target->isStdout() || $target->isStderr())
        {
            self::$OutputTargets[] = $target;
        }
        else
        {
            self::$LogTargets[] = $target;
        }

        self::$Targets[] = $target;
    }

    private static function checkLoggedOnce(string $method, string $msg1, ?string $msg2): int
    {
        $hash = Generate::hash($method, $msg1, $msg2);
        self::$LoggedOnce[$hash] = self::$LoggedOnce[$hash] ?? 0;

        return self::$LoggedOnce[$hash]++;
    }

    private static function write(
        int $level,
        string $msg1,
        ?string $msg2,
        string $pre,
        string $clr1,
        string $clr2,
        string $clrP  = null,
        Throwable $ex = null,
        bool $ttyOnly = false
    )
    {
        self::checkGroupDepth();
        $margin = self::$GroupDepth * 4;

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

        $msg = str_repeat(" ", $margin) . $pre . self::format($msg1) . self::format($msg2 ?: "");

        $clrP   = !is_null($clrP) ? $clrP : ConsoleColour::BOLD . $clr2;
        $clr1   = !self::hasBold($msg1) ? $clr1 : str_replace(ConsoleColour::BOLD, "", $clr1);
        $ttyMsg = (str_repeat(" ", $margin)
            . $clrP . $pre . ($clrP ? ConsoleColour::RESET : "")
            . $clr1 . self::format($msg1, true) . ($clr1 ? ConsoleColour::RESET : "")
            . ($msg2 ? $clr2 . self::format($msg2 ?: "", true) . ($clr2 ? ConsoleColour::RESET : "") : ""));

        $context = [];

        if ($ex)
        {
            // As per PSR-3
            $context["exception"] = $ex;
        }

        self::print($msg, $ttyMsg, $level, $context, $ttyOnly, true);
    }

    private static function print(
        string $plain,
        string $tty     = null,
        int $level      = ConsoleLevel::INFO,
        array $context  = [],
        bool $ttyOnly   = false,
        bool $formatted = false,
        array $targets  = null
    )
    {
        $tty = is_null($tty) ? $plain : $tty;

        if (!$formatted)
        {
            $plain = self::format($plain);
            $tty   = self::format($tty, true);
        }

        if (is_null($targets))
        {
            self::checkTargets();
            $targets = self::$Targets;
        }

        foreach ($targets as $target)
        {
            if ($ttyOnly && !$target->isTty())
            {
                continue;
            }

            if ($target instanceof Stream && $target->addColour())
            {
                $target->write($tty, $context, $level);
            }
            else
            {
                $target->write($plain, $context, $level);
            }
        }
    }

    public static function printTo(string $string, ConsoleTarget...$targets)
    {
        self::print($string, null, ConsoleLevel::INFO, [], false, false, $targets);
    }

    /**
     * Increase indent and print "<<< $msg1 $msg2"
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function group(string $msg1, string $msg2 = null)
    {
        if (!is_null(self::checkGroupDepth()))
        {
            self::$GroupDepth++;
        }

        self::write(
            ConsoleLevel::NOTICE,
            $msg1,
            $msg2,
            ">>> ",
            ConsoleColour::BOLD,
            ConsoleColour::CYAN
        );
    }

    /**
     * Decrease indent
     *
     */
    public static function groupEnd()
    {
        self::checkGroupDepth();
        self::$GroupDepth--;
        self::$GroupDepth = self::$GroupDepth < 0 ? null : self::$GroupDepth;
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2"
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @param int $depth Passed to {@see Err::getCaller()}. To print your
     * caller's name instead of your own, set `$depth` = 2.
     */
    public static function debug(string $msg1, string $msg2 = null,
        Throwable $ex = null, int $depth = 0)
    {
        $caller = Err::getCaller($depth);
        self::write(
            ConsoleLevel::DEBUG,
            "{{$caller}} __" . $msg1 . "__",
            $msg2,
            "--- ",
            ConsoleColour::DIM,
            ConsoleColour::DIM,
            null,
            $ex
        );
    }

    /**
     * Print " -> $msg1 $msg2"
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function log(string $msg1, string $msg2 = null,
        Throwable $ex = null)
    {
        self::write(
            ConsoleLevel::INFO,
            $msg1,
            $msg2,
            " -> ",
            "",
            ConsoleColour::YELLOW,
            null,
            $ex
        );
    }

    /**
     * Print " -> $msg1 $msg2" to TTY targets only
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function logProgress(string $msg1, string $msg2 = null,
        Throwable $ex = null)
    {
        self::write(
            ConsoleLevel::INFO,
            $msg1,
            $msg2,
            " -> ",
            "",
            ConsoleColour::YELLOW,
            null,
            $ex,
            true
        );
    }

    /**
     * Print "==> $msg1 $msg2"
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function info(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        self::write(
            ConsoleLevel::NOTICE,
            $msg1,
            $msg2,
            "==> ",
            ConsoleColour::BOLD,
            ConsoleColour::CYAN,
            null,
            $ex
        );
    }

    /**
     * Print " :: $msg1 $msg2"
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function warn(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        self::$Warnings++;
        self::write(
            ConsoleLevel::WARNING,
            $msg1,
            $msg2,
            "  ! ",
            ConsoleColour::YELLOW . ConsoleColour::BOLD,
            "",
            ConsoleColour::YELLOW . ConsoleColour::BOLD,
            $ex
        );
    }

    /**
     * Print " !! $msg1 $msg2"
     *
     * @param string $msg1
     * @param string|null $msg2
     */
    public static function error(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        self::$Errors++;
        self::write(
            ConsoleLevel::ERROR,
            $msg1,
            $msg2,
            " !! ",
            ConsoleColour::RED . ConsoleColour::BOLD,
            "",
            ConsoleColour::RED . ConsoleColour::BOLD,
            $ex
        );
    }

    public static function exception(Throwable $exception, bool $willQuit = false)
    {
        $msg2 = "";
        $ex   = $exception;
        $i    = 0;

        do
        {
            $msg2 .= (($i ? "\nCaused by __" . get_class($ex) . "__: " : "") .
                sprintf("__[[__%s__]]__,, in %s:%d,,", $ex->getMessage(), $ex->getFile(), $ex->getLine()));
            $ex = $ex->getPrevious();
            $i++;
        }
        while ($ex);

        // If this is the first and only call to an output function before the
        // running script succumbs to $exception, don't risk adding filesystem
        // errors to the mix
        if ($willQuit && !self::$TargetsChecked)
        {
            self::$AutomaticLogTarget = false;
        }

        self::error("Uncaught __" . get_class($exception) . "__:", $msg2, $exception);
        self::write(
            ConsoleLevel::DEBUG,
            "__Stack trace:__",
            "__[[__" . $exception->getTraceAsString() . "__]]__",
            "--- ",
            ConsoleColour::DIM,
            ConsoleColour::DIM,
            null,
            $exception
        );
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @param int $depth
     */
    public static function debugOnce(string $msg1, string $msg2 = null, Throwable $ex = null, int $depth = 0)
    {
        if (!self::checkLoggedOnce(__METHOD__, $msg1, $msg2))
        {
            self::debug($msg1, $msg2, $ex, $depth + 1);
        }
    }

    /**
     * Print " -> $msg1 $msg2" unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     */
    public static function logOnce(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        if (!self::checkLoggedOnce(__METHOD__, $msg1, $msg2))
        {
            self::log($msg1, $msg2, $ex);
        }
    }

    /**
     * Print "==> $msg1 $msg2" unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     */
    public static function infoOnce(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        if (!self::checkLoggedOnce(__METHOD__, $msg1, $msg2))
        {
            self::info($msg1, $msg2, $ex);
        }
    }

    /**
     * Print " :: $msg1 $msg2" unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     */
    public static function warnOnce(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        if (!self::checkLoggedOnce(__METHOD__, $msg1, $msg2))
        {
            self::warn($msg1, $msg2, $ex);
        }
    }

    /**
     * Print " !! $msg1 $msg2" unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     */
    public static function errorOnce(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        if (!self::checkLoggedOnce(__METHOD__, $msg1, $msg2))
        {
            self::error($msg1, $msg2, $ex);
        }
    }

    public static function getWarnings(): int
    {
        return self::$Warnings;
    }

    public static function getErrors(): int
    {
        return self::$Errors;
    }

    public static function getSummary($successText = " without errors", bool $reset = true): string
    {
        if (self::$Warnings + self::$Errors)
        {
            $summary = " with " . Convert::numberToNoun(self::$Errors, "error", null, true);

            if (self::$Warnings)
            {
                $summary .= " and " . Convert::numberToNoun(self::$Warnings, "warning", null, true);
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

