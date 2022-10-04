<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Contract\ReceivesFacade;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Debug;
use Throwable;

/**
 * Base class for Console
 *
 */
abstract class ConsoleWriter implements ReceivesFacade
{
    /**
     * @var int
     */
    protected $GroupLevel = -1;

    /**
     * @var int
     */
    protected $Errors = 0;

    /**
     * @var int
     */
    protected $Warnings = 0;

    /**
     * Message hash => counter
     *
     * @var array<string,int>
     */
    protected $Written = [];

    /**
     * @var string|null
     */
    private $Facade;

    final public function setFacade(string $name): void
    {
        $this->Facade = $name;
    }

    /**
     * Get the number of errors reported so far
     *
     */
    final public function getErrors(): int
    {
        return $this->Errors;
    }

    /**
     * Get the number of warnings reported so far
     *
     */
    final public function getWarnings(): int
    {
        return $this->Warnings;
    }

    /**
     * Print a "command finished" message with a summary of errors and warnings
     *
     * Prints " // $finishedText $successText" with level INFO if no errors or
     * warnings have been reported (default: " // Command finished without
     * errors").
     *
     * Otherwise, prints one of the following with level ERROR or WARNING:
     * - " !! $finishedText with $errors errors[ and $warnings warnings]"
     * - "  ! $finishedText with 0 errors and $warnings warnings"
     *
     * @return $this
     */
    final public function summary(string $finishedText = "Command finished", string $successText = "without errors")
    {
        $msg1 = trim($finishedText);
        if (!($this->Warnings || $this->Errors))
        {
            return $this->write(Level::INFO, $msg1, $successText, " // ");
        }

        $msg2 = "with " . Convert::numberToNoun($this->Errors, "error", "errors", true);
        if ($this->Warnings)
        {
            $msg2 .= " and " . Convert::numberToNoun($this->Warnings, "warning", "warnings", true);
        }
        return $this->write(
            $this->Errors ? Level::ERROR : Level::WARNING,
            $msg1,
            $msg2,
            $this->Errors ? " !! " : "  ! "
        );
    }

    /**
     * Send a message to registered targets
     *
     * @return $this
     */
    abstract protected function write(int $level, string $msg1, ?string $msg2, string $prefix, ?Throwable $ex = null);

    /**
     * Send a message to registered TTY targets
     *
     * @return $this
     */
    abstract protected function writeTty(int $level, string $msg1, ?string $msg2, string $prefix, ?Throwable $ex = null);

    /**
     * Send a message to registered targets once per run
     *
     * @return $this
     */
    final protected function writeOnce(int $level, string $msg1, ?string $msg2, string $prefix, ?Throwable $ex = null)
    {
        $hash = Compute::hash($level, $msg1, $msg2, $prefix);
        if (($this->Written[$hash] = ($this->Written[$hash] ?? 0) + 1) < 2)
        {
            return $this->write($level, $msg1, $msg2, $prefix, $ex);
        }

        return $this;
    }

    /**
     * Print "$msg" to I/O stream targets (STDOUT or STDERR)
     *
     * @return $this
     */
    abstract public function out(string $msg, int $level = Level::INFO);

    /**
     * Print "$msg" to TTY targets
     *
     * @return $this
     */
    abstract public function tty(string $msg, int $level = Level::INFO);

    /**
     * Print " !! $msg1 $msg2" with level ERROR
     *
     * @return $this
     */
    final public function error(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        $this->Errors++;
        return $this->write(Level::ERROR, $msg1, $msg2, " !! ", $ex);
    }

    /**
     * Print " !! $msg1 $msg2" with level ERROR once per run
     *
     * @return $this
     */
    final public function errorOnce(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        $this->Errors++;
        return $this->writeOnce(Level::ERROR, $msg1, $msg2, " !! ", $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING
     *
     * @return $this
     */
    final public function warn(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        $this->Warnings++;
        return $this->write(Level::WARNING, $msg1, $msg2, "  ! ", $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING once per run
     *
     * @return $this
     */
    final public function warnOnce(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        $this->Warnings++;
        return $this->writeOnce(Level::WARNING, $msg1, $msg2, "  ! ", $ex);
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE
     *
     * @return $this
     */
    final public function info(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        return $this->write(Level::NOTICE, $msg1, $msg2, "==> ", $ex);
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE once per run
     *
     * @return $this
     */
    final public function infoOnce(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        return $this->writeOnce(Level::NOTICE, $msg1, $msg2, "==> ", $ex);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO
     *
     * @return $this
     */
    final public function log(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        return $this->write(Level::INFO, $msg1, $msg2, " -> ", $ex);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO once per run
     *
     * @return $this
     */
    final public function logOnce(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        return $this->writeOnce(Level::INFO, $msg1, $msg2, " -> ", $ex);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO to TTY targets
     *
     * @return $this
     */
    final public function logProgress(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        return $this->writeTty(Level::INFO, $msg1, $msg2, " -> ", $ex);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG
     *
     * @param int $depth Passed to {@see \Lkrms\Utility\Debugging::getCaller()}.
     * To print your caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    final public function debug(string $msg1, string $msg2 = null, ?Throwable $ex = null, int $depth = 0)
    {
        if ($this->Facade)
        {
            $depth++;
        }

        $caller = implode("", Debug::getCaller($depth));
        return $this->write(Level::DEBUG, "{{$caller}} __" . $msg1 . "__", $msg2, "--- ", $ex);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run
     *
     * @param int $depth Passed to {@see \Lkrms\Utility\Debugging::getCaller()}.
     * To print your caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    final public function debugOnce(string $msg1, string $msg2 = null, ?Throwable $ex = null, int $depth = 0)
    {
        if ($this->Facade)
        {
            $depth++;
        }

        $caller = implode("", Debug::getCaller($depth));
        return $this->writeOnce(Level::DEBUG, "{{$caller}} __" . $msg1 . "__", $msg2, "--- ", $ex);
    }

    /**
     * Create a new message group and print "<<< $msg1 $msg2" with level NOTICE
     *
     * The message group will remain open, and subsequent messages will be
     * indented, until {@see ConsoleMessageWriter::groupEnd()} is called.
     *
     * @return $this
     */
    final public function group(string $msg1, string $msg2 = null, ?Throwable $ex = null)
    {
        $this->GroupLevel++;
        return $this->write(Level::NOTICE, $msg1, $msg2, ">>> ", $ex);
    }

    /**
     * Close the most recently created message group
     *
     * @return $this
     * @see ConsoleMessageWriter::group()
     */
    final public function groupEnd()
    {
        if ($this->GroupLevel > -1)
        {
            $this->GroupLevel--;
        }

        return $this;
    }

    /**
     * Report an uncaught exception
     *
     * Prints " !! Uncaught <exception>: <message> in <file>:<line>" with level
     * ERROR, followed by the exception's stack trace with level DEBUG.
     *
     * @return $this
     */
    final public function exception(Throwable $exception)
    {
        $ex = $exception;
        $i  = 0;
        do
        {
            $msg2 = ($msg2 ?? "") . (($i++ ? "\nCaused by __" . get_class($ex) . "__: " : "")
                . sprintf("`%s` ~~in %s:%d~~",
                    ConsoleFormatter::escape($ex->getMessage()),
                    $ex->getFile(), $ex->getLine()));
            $ex = $ex->getPrevious();
        }
        while ($ex);

        $this->Errors++;
        $this->write(Level::ERROR,
            "Uncaught __" . get_class($exception) . "__:", $msg2, " !! ", $exception);
        $this->write(Level::DEBUG,
            "__Stack trace:__", "\n`" . ConsoleFormatter::escape($exception->getTraceAsString()) . "`", "--- ");
        if ($exception instanceof \Lkrms\Exception\Exception)
        {
            foreach ($exception->getDetail() as $section => $text)
            {
                $this->write(Level::DEBUG,
                    "__{$section}:__", "\n`" . ConsoleFormatter::escape($text) . "`", "--- ");
            }
        }

        return $this;
    }
}
