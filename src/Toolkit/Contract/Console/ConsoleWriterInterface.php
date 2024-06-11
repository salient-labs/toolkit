<?php declare(strict_types=1);

namespace Salient\Contract\Console;

use Psr\Log\LoggerInterface;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Throwable;

interface ConsoleWriterInterface
{
    /**
     * Register STDOUT and STDERR to receive console output if running on the
     * command line
     *
     * - Errors and warnings are written to `STDERR`
     * - Informational messages are written to `STDOUT`
     * - Debug messages are ignored unless environment variable `DEBUG` is set
     *
     * @return $this
     */
    public function registerStdioTargets();

    /**
     * Register STDERR to receive console output if running on the command line
     *
     * - Errors, warnings and informational messages are written to `STDERR`
     * - Debug messages are ignored unless environment variable `DEBUG` is set
     *
     * @return $this
     */
    public function registerStderrTarget();

    /**
     * Register a target to receive console output
     *
     * @param array<Level::*> $levels
     * @return $this
     */
    public function registerTarget(
        ConsoleTargetInterface $target,
        array $levels = LevelGroup::ALL
    );

    /**
     * Deregister and close a registered target
     *
     * If `$target` is registered with the writer, it is deregistered and closed
     * via {@see ConsoleTargetInterface::close()}, otherwise calling this method
     * has no effect.
     *
     * @return $this
     */
    public function deregisterTarget(ConsoleTargetInterface $target);

    /**
     * Get a list of registered targets, optionally filtered by level and type
     *
     * @param Level::*|null $level
     * @param int-mask-of<ConsoleTargetTypeFlag::*> $flags
     * @return ConsoleTargetInterface[]
     */
    public function getTargets(?int $level = null, int $flags = 0): array;

    /**
     * Set or unset the prefix applied to each line of output by any registered
     * targets that implement ConsoleTargetPrefixInterface
     *
     * @param int-mask-of<ConsoleTargetTypeFlag::*> $flags
     * @return $this
     */
    public function setTargetPrefix(?string $prefix, int $flags = 0);

    /**
     * Get the width of a registered target in columns
     *
     * Returns {@see ConsoleTargetInterface::getWidth()} from whichever is found
     * first:
     *
     * - the first TTY target registered with the given level
     * - the first `STDOUT` or `STDERR` target registered with the given level
     * - the first target registered with the given level
     * - the target returned by {@see getStderrTarget()} if backed by a TTY
     * - the target returned by {@see getStdoutTarget()}
     *
     * @param Level::* $level
     */
    public function getWidth(int $level = Level::INFO): ?int;

    /**
     * Get an output formatter for a registered target
     *
     * Returns {@see ConsoleTargetInterface::getFormatter()} from the same
     * target as {@see ConsoleWriterInterface::getWidth()}.
     *
     * @param Level::* $level
     */
    public function getFormatter(int $level = Level::INFO): Formatter;

    /**
     * Get a PSR-3 logger backed by the writer
     */
    public function getLogger(): LoggerInterface;

    /**
     * Get a target for STDOUT, creating an unregistered one if necessary
     */
    public function getStdoutTarget(): ConsoleTargetStreamInterface;

    /**
     * Get a target for STDERR, creating an unregistered one if necessary
     */
    public function getStderrTarget(): ConsoleTargetStreamInterface;

    /**
     * Get the number of error messages recorded by the writer so far
     */
    public function getErrorCount(): int;

    /**
     * Get the number of warning messages recorded by the writer so far
     */
    public function getWarningCount(): int;

    /**
     * Print "‼ $msg1 $msg2" with level ERROR
     *
     * @return $this
     */
    public function error(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "‼ $msg1 $msg2" with level ERROR once per run
     *
     * @return $this
     */
    public function errorOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "! $msg1 $msg2" with level WARNING
     *
     * @return $this
     */
    public function warn(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "! $msg1 $msg2" with level WARNING once per run
     *
     * @return $this
     */
    public function warnOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "➤ $msg1 $msg2" with level NOTICE
     *
     * @return $this
     */
    public function info(string $msg1, ?string $msg2 = null);

    /**
     * Print "➤ $msg1 $msg2" with level NOTICE once per run
     *
     * @return $this
     */
    public function infoOnce(string $msg1, ?string $msg2 = null);

    /**
     * Print "- $msg1 $msg2" with level INFO
     *
     * @return $this
     */
    public function log(string $msg1, ?string $msg2 = null);

    /**
     * Print "- $msg1 $msg2" with level INFO once per run
     *
     * @return $this
     */
    public function logOnce(string $msg1, ?string $msg2 = null);

    /**
     * Print "⁞ <caller> $msg1 $msg2" with level DEBUG
     *
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
     * Print "⁞ <caller> $msg1 $msg2" with level DEBUG once per run
     *
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
     * Print "⠿ $msg1 $msg2" with level INFO to TTY targets without moving to
     * the next line
     *
     * This method can be called repeatedly to display progress updates without
     * disrupting other console messages or bloating output logs.
     *
     * @return $this
     */
    public function logProgress(string $msg1, ?string $msg2 = null);

    /**
     * Print a "clear to end of line" control sequence with level INFO to TTY
     * targets with a logProgress() message
     *
     * @return $this
     */
    public function clearProgress();

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     *
     * @param Level::* $level
     * @param ConsoleMessageType::* $type
     * @return $this
     */
    public function message(
        int $level,
        string $msg1,
        ?string $msg2 = null,
        int $type = ConsoleMessageType::STANDARD,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     * once per run
     *
     * @param Level::* $level
     * @param ConsoleMessageType::* $type
     * @return $this
     */
    public function messageOnce(
        int $level,
        string $msg1,
        ?string $msg2 = null,
        int $type = ConsoleMessageType::STANDARD,
        ?Throwable $ex = null,
        bool $count = true
    );

    /**
     * Record a message with level $level without printing anything
     *
     * @param Level::* $level
     * @return $this
     */
    public function count(int $level);

    /**
     * Increase the indentation level of messages and print "▶ $msg1 $msg2" with
     * level NOTICE
     *
     * If `$endMsg1` is not `null`, `"◀ $endMsg1 $endMsg2"` is printed with
     * level `NOTICE` when {@see groupEnd()} is called to close the group.
     *
     * @return $this
     */
    public function group(
        string $msg1,
        ?string $msg2 = null,
        ?string $endMsg1 = null,
        ?string $endMsg2 = null
    );

    /**
     * Close the nested message group most recently opened with group()
     *
     * @return $this
     */
    public function groupEnd();

    /**
     * Print an exception with level $level (default: ERROR) and its stack trace
     * with level $traceLevel (default: DEBUG)
     *
     * @param Level::* $level
     * @param Level::*|null $traceLevel If `null`, the exception's stack
     * trace is not printed.
     * @return $this
     */
    public function exception(
        Throwable $exception,
        int $level = Level::ERROR,
        ?int $traceLevel = Level::DEBUG
    );

    /**
     * Print a "command finished" message with a summary of errors, warnings and
     * resource usage
     *
     * @return $this
     */
    public function summary(
        string $finishedText = 'Command finished',
        string $successText = 'without errors',
        bool $withResourceUsage = false,
        bool $withoutErrorCount = false,
        bool $withStandardMessageType = false
    );

    /**
     * Print "$msg" to registered targets
     *
     * @param Level::* $level
     * @param ConsoleMessageType::* $type
     * @return $this
     */
    public function print(
        string $msg,
        int $level = Level::INFO,
        int $type = ConsoleMessageType::UNFORMATTED
    );

    /**
     * Print "$msg" to registered STDOUT or STDERR targets
     *
     * @param Level::* $level
     * @param ConsoleMessageType::* $type
     * @return $this
     */
    public function printOut(
        string $msg,
        int $level = Level::INFO,
        int $type = ConsoleMessageType::UNFORMATTED
    );

    /**
     * Print "$msg" to registered TTY targets
     *
     * @param Level::* $level
     * @param ConsoleMessageType::* $type
     * @return $this
     */
    public function printTty(
        string $msg,
        int $level = Level::INFO,
        int $type = ConsoleMessageType::UNFORMATTED
    );

    /**
     * Print "$msg" to STDOUT even if no STDOUT target is registered
     *
     * @param Level::* $level
     * @param ConsoleMessageType::* $type
     * @return $this
     */
    public function printStdout(
        string $msg,
        int $level = Level::INFO,
        int $type = ConsoleMessageType::UNFORMATTED
    );

    /**
     * Print "$msg" to STDERR even if no STDERR target is registered
     *
     * @param Level::* $level
     * @param ConsoleMessageType::* $type
     * @return $this
     */
    public function printStderr(
        string $msg,
        int $level = Level::INFO,
        int $type = ConsoleMessageType::UNFORMATTED
    );
}
