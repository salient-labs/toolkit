<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\ConsoleTarget\ConsoleTarget;
use Lkrms\Util\Convert;
use Lkrms\Util\Runtime;
use Throwable;

/**
 * Base class for Console
 *
 */
abstract class ConsoleMessageWriter
{
    /**
     * @var int
     */
    protected static $GroupLevel = -1;

    /**
     * @var int
     */
    protected static $Errors = 0;

    /**
     * @var int
     */
    protected static $Warnings = 0;

    /**
     * Get the number of errors reported so far
     *
     * @return int
     */
    public static function getErrors(): int
    {
        return self::$Errors;
    }

    /**
     * Get the number of warnings reported so far
     *
     * @return int
     */
    public static function getWarnings(): int
    {
        return self::$Warnings;
    }

    /**
     * Get a "command finished" message with error and/or warning tallies
     *
     * Returns `"$finishedText $successText"` (default: `"Command finished
     * without errors"`) if no errors or warnings have been reported, otherwise
     * `"$finishedText with X errors[ and Y warnings]"` (example: `"Command
     * finished with 1 error and 2 warnings"`).
     *
     * Usage suggestion:
     *
     * ```php
     * Console::info(Console::getSummary("Sync completed"));
     * ```
     *
     * @param string $finishedText
     * @param string $successText
     * @param bool $reset Reset error and warning counters? (default: `true`)
     * @return string
     */
    public static function getSummary(
        string $finishedText = "Command finished",
        string $successText  = "without errors",
        bool $reset          = true
    ): string
    {
        $msg = trim($finishedText) . " ";

        if (self::$Warnings + self::$Errors)
        {
            $msg .= "with " . Convert::numberToNoun(self::$Errors, "error", "errors", true);

            if (self::$Warnings)
            {
                $msg .= " and " . Convert::numberToNoun(self::$Warnings, "warning", "warnings", true);
            }

            if ($reset)
            {
                self::$Warnings = self::$Errors = 0;
            }

            return $msg;
        }
        else
        {
            return $msg . trim($successText);
        }
    }

    /**
     * Forward a message to registered targets
     *
     * @param int $level
     * @param string $msg1
     * @param null|string $msg2
     * @param string $prefix
     * @param Throwable|null $ex
     * @param bool $ttyOnly
     */
    abstract protected static function write(
        int $level,
        string $msg1,
        ?string $msg2,
        string $prefix,
        Throwable $ex = null,
        bool $ttyOnly = false
    );

    /**
     * Print "$msg" with level INFO to particular targets
     *
     * If no targets are specified, print to every registered target.
     *
     * @param string $msg
     * @param ConsoleTarget ...$targets
     * @see ConsoleLevel::INFO
     */
    abstract public static function printTo(
        string $msg,
        ConsoleTarget ...$targets
    );

    /**
     * Increment a counter for a specific message and return its previous value
     *
     * @param string $method
     * @param string $msg1
     * @param null|string $msg2
     * @return int
     */
    abstract protected static function logWrite(
        string $method,
        string $msg1,
        ?string $msg2
    ): int;

    /**
     * Print " !! $msg1 $msg2" with level ERROR
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::ERROR
     */
    public static function error(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        self::$Errors++;
        static::write(ConsoleLevel::ERROR, $msg1, $msg2, " !! ", $ex);
    }

    /**
     * Print " !! $msg1 $msg2" with level ERROR unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::ERROR
     */
    public static function errorOnce(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        if (! static::logWrite(__METHOD__, $msg1, $msg2))
        {
            self::$Errors++;
            static::write(ConsoleLevel::ERROR, $msg1, $msg2, " !! ", $ex);
        }
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::WARNING
     */
    public static function warn(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        self::$Warnings++;
        static::write(ConsoleLevel::WARNING, $msg1, $msg2, "  ! ", $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::WARNING
     */
    public static function warnOnce(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        if (! static::logWrite(__METHOD__, $msg1, $msg2))
        {
            self::$Warnings++;
            static::write(ConsoleLevel::WARNING, $msg1, $msg2, "  ! ", $ex);
        }
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::NOTICE
     */
    public static function info(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        static::write(ConsoleLevel::NOTICE, $msg1, $msg2, "==> ", $ex);
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::NOTICE
     */
    public static function infoOnce(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        if (! static::logWrite(__METHOD__, $msg1, $msg2))
        {
            static::write(ConsoleLevel::NOTICE, $msg1, $msg2, "==> ", $ex);
        }
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::INFO
     */
    public static function log(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        static::write(ConsoleLevel::INFO, $msg1, $msg2, " -> ", $ex);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::INFO
     */
    public static function logOnce(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        if (! static::logWrite(__METHOD__, $msg1, $msg2))
        {
            static::write(ConsoleLevel::INFO, $msg1, $msg2, " -> ", $ex);
        }
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO (TTY targets only)
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::INFO
     */
    public static function logProgress(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        static::write(ConsoleLevel::INFO, $msg1, $msg2, " -> ", $ex, true);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @param int $depth Passed to {@see Runtime::getCaller()}. To print your
     * caller's name instead of your own, set `$depth` to 1.
     * @see ConsoleLevel::DEBUG
     */
    public static function debug(string $msg1, string $msg2 = null, Throwable $ex = null, int $depth = 0)
    {
        $caller = implode("", Runtime::getCaller($depth));
        static::write(ConsoleLevel::DEBUG, "{{$caller}} __" . $msg1 . "__", $msg2, "--- ", $ex);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG unless already printed
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @param int $depth Passed to {@see Runtime::getCaller()}. To print your
     * caller's name instead of your own, set `$depth` to 1.
     * @see ConsoleLevel::DEBUG
     */
    public static function debugOnce(string $msg1, string $msg2 = null, Throwable $ex = null, int $depth = 0)
    {
        if (! static::logWrite(__METHOD__, $msg1, $msg2))
        {
            $caller = implode("", Runtime::getCaller($depth));
            static::write(ConsoleLevel::DEBUG, "{{$caller}} __" . $msg1 . "__", $msg2, "--- ", $ex);
        }
    }

    /**
     * Create a new message group and print "<<< $msg1 $msg2" with level NOTICE
     *
     * The message group will remain open, and subsequent messages will be
     * indented, until {@see ConsoleMessageWriter::groupEnd()} is called.
     *
     * @param string $msg1
     * @param string|null $msg2
     * @param Throwable|null $ex
     * @see ConsoleLevel::NOTICE
     */
    public static function group(string $msg1, string $msg2 = null, Throwable $ex = null)
    {
        self::$GroupLevel++;
        static::write(ConsoleLevel::NOTICE, $msg1, $msg2, ">>> ", $ex);
    }

    /**
     * Close the most recently created message group
     *
     * @see ConsoleMessageWriter::group()
     */
    public static function groupEnd()
    {
        if (self::$GroupLevel > -1)
        {
            self::$GroupLevel--;
        }
    }

    /**
     * Report an uncaught exception
     *
     * Print " !! Uncaught <exception>: <message> in <file>:<line>" with level
     * ERROR, then print the exception's stack trace with level DEBUG.
     *
     * @param Throwable $exception
     * @see ConsoleLevel::ERROR
     * @see ConsoleLevel::DEBUG
     */
    public static function exception(Throwable $exception)
    {
        $ex = $exception;
        $i  = 0;

        do
        {
            $msg2 = ($msg2 ?? "") . (($i ? "\nCaused by __" . get_class($ex) . "__: " : "") . sprintf(
                "`%s` ~~in %s:%d~~",
                ConsoleText::escape($ex->getMessage()),
                $ex->getFile(),
                $ex->getLine()
            ));
            $ex = $ex->getPrevious();
            $i++;
        }
        while ($ex);

        self::$Errors++;
        static::write(
            ConsoleLevel::ERROR,
            "Uncaught __" . get_class($exception) . "__:",
            $msg2,
            " !! ",
            $exception
        );
        static::write(
            ConsoleLevel::DEBUG,
            "__Stack trace:__",
            "\n`" . ConsoleText::escape($exception->getTraceAsString()) . "`",
            "--- ",
            $exception
        );
        if ($exception instanceof \Lkrms\Exception\Exception)
        {
            foreach ($exception->getDetail() as $section => $text)
            {
                static::write(
                    ConsoleLevel::DEBUG,
                    "__{$section}:__",
                    "\n`" . ConsoleText::escape($text) . "`",
                    "--- ",
                    $exception
                );
            }
        }
    }
}
