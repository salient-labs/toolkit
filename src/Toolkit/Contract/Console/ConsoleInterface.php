<?php declare(strict_types=1);

namespace Salient\Contract\Console;

use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Salient\Contract\Console\Target\StreamTargetInterface;
use Salient\Contract\Console\Target\TargetInterface;
use Salient\Contract\Core\Instantiable;
use Salient\Contract\HasMessageLevel;
use Salient\Contract\HasMessageLevels;
use Throwable;

/**
 * @api
 */
interface ConsoleInterface extends
    Instantiable,
    HasMessageLevel,
    HasMessageLevels,
    HasMessageType,
    HasMessageTypes,
    HasTargetFlag
{
    /**
     * Get a PSR-3 logger backed by the console
     */
    public function logger(): PsrLoggerInterface;

    /**
     * Register a target to receive output with each of the given message levels
     * from the console
     *
     * @param array<ConsoleInterface::LEVEL_*> $levels
     * @return $this
     */
    public function registerTarget(
        TargetInterface $target,
        array $levels = ConsoleInterface::LEVELS_ALL
    );

    /**
     * Deregister a target if it is currently registered with the console
     *
     * @return $this
     */
    public function deregisterTarget(TargetInterface $target);

    /**
     * Register STDERR for console output if running on the command line
     *
     * Messages with level `LEVEL_DEBUG` are written to `STDERR` if:
     *
     * - `$debug` is `true`, or
     * - `$debug` is `null` and debug mode is enabled in the environment
     *
     * @return $this
     */
    public function registerStderrTarget(?bool $debug = null);

    /**
     * Register STDOUT and STDERR for console output if running on the command
     * line
     *
     * If the level of a message is `LEVEL_WARNING` or lower, it is written to
     * `STDERR`, otherwise it is written to `STDOUT`.
     *
     * Messages with level `LEVEL_DEBUG` are written to `STDOUT` if:
     *
     * - `$debug` is `true`, or
     * - `$debug` is `null` and debug mode is enabled in the environment
     *
     * @return $this
     */
    public function registerStdioTargets(?bool $debug = null);

    /**
     * Set or unset the prefix applied to each line of output by registered
     * targets that implement HasPrefix after optionally filtering them by flag
     *
     * @param int-mask-of<ConsoleInterface::TARGET_*> $targetFlags
     * @return $this
     */
    public function setPrefix(?string $prefix, int $targetFlags = 0);

    /**
     * Get registered targets, optionally filtering them by message level and
     * flag
     *
     * @param ConsoleInterface::LEVEL_*|null $level
     * @param int-mask-of<ConsoleInterface::TARGET_*> $targetFlags
     * @return TargetInterface[]
     */
    public function getTargets(?int $level = null, int $targetFlags = 0): array;

    /**
     * Get a target for STDOUT, creating an unregistered one if necessary
     */
    public function getStdoutTarget(): StreamTargetInterface;

    /**
     * Get a target for STDERR, creating an unregistered one if necessary
     */
    public function getStderrTarget(): StreamTargetInterface;

    /**
     * Get a TTY target, creating an unregistered one if necessary
     *
     * Returns a target for `STDERR` if neither or both of `STDOUT` and `STDERR`
     * refer to an interactive terminal.
     */
    public function getTtyTarget(): StreamTargetInterface;

    /**
     * Escape inline formatting tags in a string so it can be safely used in a
     * console message
     */
    public function escape(string $string, bool $escapeNewlines = false): string;

    /**
     * Remove escapes from inline formatting tags in a string
     */
    public function removeEscapes(string $string): string;

    /**
     * Remove inline formatting tags from a string
     */
    public function removeTags(string $string): string;

    /**
     * Print "! $msg1 $msg2" or similar with level LEVEL_ERROR
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function error(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "! $msg1 $msg2" or similar with level LEVEL_ERROR once per run
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function errorOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "^ $msg1 $msg2" or similar with level LEVEL_WARNING
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function warn(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "^ $msg1 $msg2" or similar with level LEVEL_WARNING once per run
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function warnOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Increase message indentation level and print "» $msg1 $msg2" or similar
     * with level LEVEL_NOTICE
     *
     * If `$endMsg1` or `$endMsg2` are not `null`, `"« $endMsg1 $endMsg2"` or
     * similar is printed with level `LEVEL_NOTICE` when {@see groupEnd()} is
     * called to close the group.
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @param string|null $endMsg1 May use inline formatting tags.
     * @param string|null $endMsg2 Inline formatting tags have no special
     * meaning.
     * @return $this
     */
    public function group(
        string $msg1,
        ?string $msg2 = null,
        ?string $endMsg1 = null,
        ?string $endMsg2 = null
    );

    /**
     * Close the group of messages most recently opened with group()
     *
     * @return $this
     */
    public function groupEnd();

    /**
     * Print "> $msg1 $msg2" or similar with level LEVEL_NOTICE
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function info(string $msg1, ?string $msg2 = null);

    /**
     * Print "> $msg1 $msg2" or similar with level LEVEL_NOTICE once per run
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function infoOnce(string $msg1, ?string $msg2 = null);

    /**
     * Print "- $msg1 $msg2" or similar with level LEVEL_INFO
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function log(string $msg1, ?string $msg2 = null);

    /**
     * Print "- $msg1 $msg2" or similar with level LEVEL_INFO once per run
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function logOnce(string $msg1, ?string $msg2 = null);

    /**
     * Print "⠋ $msg1 $msg2" or similar with level LEVEL_INFO to TTY targets
     * without moving to the next line
     *
     * May be called repeatedly to display progress updates without disrupting
     * other console messages or bloating output logs.
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @return $this
     */
    public function logProgress(string $msg1, ?string $msg2 = null);

    /**
     * Clear the message most recently printed with logProgress()
     *
     * @return $this
     */
    public function clearProgress();

    /**
     * Print ": <caller> $msg1 $msg2" or similar with level LEVEL_DEBUG
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @param int $depth To print your caller's name instead of your own, set
     * `$depth` to 1.
     * @return $this
     */
    public function debug(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        int $depth = 0
    );

    /**
     * Print ": <caller> $msg1 $msg2" or similar with level LEVEL_DEBUG once per
     * run
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @param int $depth To print your caller's name instead of your own, set
     * `$depth` to 1.
     * @return $this
     */
    public function debugOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        int $depth = 0
    );

    /**
     * Print "$msg1 $msg2" or similar with level- and type-based formatting
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @param ConsoleInterface::LEVEL_* $level
     * @param ConsoleInterface::TYPE_* $type
     * @return $this
     */
    public function message(
        string $msg1,
        ?string $msg2 = null,
        int $level = ConsoleInterface::LEVEL_INFO,
        int $type = ConsoleInterface::TYPE_UNDECORATED,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "$msg1 $msg2" or similar with level- and type-based formatting once
     * per run
     *
     * @param string $msg1 May use inline formatting tags (see {@see escape()}).
     * @param string|null $msg2 Inline formatting tags have no special meaning.
     * @param ConsoleInterface::LEVEL_* $level
     * @param ConsoleInterface::TYPE_* $type
     * @return $this
     */
    public function messageOnce(
        string $msg1,
        ?string $msg2 = null,
        int $level = ConsoleInterface::LEVEL_INFO,
        int $type = ConsoleInterface::TYPE_UNDECORATED,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print an exception and its stack trace with the given message levels
     *
     * @param ConsoleInterface::LEVEL_* $level
     * @param ConsoleInterface::LEVEL_*|null $traceLevel If `null`, the
     * exception's stack trace is not printed.
     * @return $this
     */
    public function exception(
        Throwable $exception,
        int $level = ConsoleInterface::LEVEL_ERROR,
        ?int $traceLevel = ConsoleInterface::LEVEL_DEBUG,
        bool $count = true
    );

    /**
     * Print a "command finished" message with a summary of errors, warnings and
     * resource usage
     *
     * @param string $finishedText May use inline formatting tags (see
     * {@see escape()}).
     * @param string $successText May use inline formatting tags.
     * @return $this
     */
    public function summary(
        string $finishedText = 'Command finished',
        string $successText = 'without errors',
        bool $withResourceUsage = false,
        bool $withoutErrorsAndWarnings = false,
        bool $withGenericType = false
    );

    /**
     * Print "$msg" to registered targets
     *
     * @param string $msg May use inline formatting tags (see {@see escape()}).
     * @param ConsoleInterface::LEVEL_* $level
     * @return $this
     */
    public function print(
        string $msg,
        int $level = ConsoleInterface::LEVEL_INFO
    );

    /**
     * Print "$msg" to registered targets that write to STDOUT or STDERR
     *
     * @param string $msg May use inline formatting tags (see {@see escape()}).
     * @param ConsoleInterface::LEVEL_* $level
     * @return $this
     */
    public function printStdio(
        string $msg,
        int $level = ConsoleInterface::LEVEL_INFO
    );

    /**
     * Print "$msg" to registered targets that write to a TTY
     *
     * @param string $msg May use inline formatting tags (see {@see escape()}).
     * @param ConsoleInterface::LEVEL_* $level
     * @return $this
     */
    public function printTty(
        string $msg,
        int $level = ConsoleInterface::LEVEL_INFO
    );

    /**
     * Print "$msg" to STDOUT, creating an unregistered target if necessary
     *
     * @param string $msg May use inline formatting tags (see {@see escape()}).
     * @param ConsoleInterface::LEVEL_* $level
     * @return $this
     */
    public function printStdout(
        string $msg,
        int $level = ConsoleInterface::LEVEL_INFO
    );

    /**
     * Record a message with the given level without printing anything
     *
     * @param ConsoleInterface::LEVEL_* $level
     * @return $this
     */
    public function count(int $level);

    /**
     * Get the number of error messages recorded so far
     */
    public function errors(): int;

    /**
     * Get the number of warning messages recorded so far
     */
    public function warnings(): int;
}
