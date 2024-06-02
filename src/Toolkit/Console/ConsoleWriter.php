<?php declare(strict_types=1);

namespace Salient\Console;

use Salient\Console\Support\ConsoleWriterState;
use Salient\Console\Target\StreamTarget;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleTargetInterface as Target;
use Salient\Contract\Console\ConsoleTargetPrefixInterface as TargetPrefix;
use Salient\Contract\Console\ConsoleTargetStreamInterface as TargetStream;
use Salient\Contract\Console\ConsoleTargetTypeFlag as TargetTypeFlag;
use Salient\Contract\Core\ExceptionInterface;
use Salient\Contract\Core\FacadeAwareInterface;
use Salient\Contract\Core\FacadeInterface;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Contract\Core\MultipleErrorExceptionInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\HasFacade;
use Salient\Core\Exception\InvalidEnvironmentException;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Debug;
use Salient\Core\Utility\Env;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Format;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Sys;
use Throwable;

/**
 * Logs messages to registered targets
 *
 * {@see ConsoleWriter} methods should generally be called via the
 * {@see Console} facade. If a {@see ConsoleWriter} instance is required, call
 * {@see Console::getInstance()}.
 *
 * @implements FacadeAwareInterface<FacadeInterface<self>>
 */
final class ConsoleWriter implements FacadeAwareInterface, Unloadable
{
    /** @use HasFacade<FacadeInterface<self>> */
    use HasFacade;

    private ConsoleWriterState $State;

    public function __construct()
    {
        $this->State = new ConsoleWriterState();
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        $this->deregisterAllTargets();
    }

    /**
     * Register a log file to receive console output
     *
     * Output is appended to a file created with mode `0600` in
     * {@see sys_get_temp_dir()}:
     *
     * ```php
     * <?php
     * sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
     * ```
     *
     * @return $this
     */
    public function registerLogTarget()
    {
        return $this->registerTarget(
            StreamTarget::fromPath(File::getStablePath('.log')),
            LevelGroup::ALL
        );
    }

    /**
     * Register STDOUT or STDERR to receive console output if a preferred target
     * is found in the environment and no other standard output targets are
     * registered
     *
     * Otherwise, register `STDERR` if it is a TTY and `STDOUT` is not, or call
     * {@see ConsoleWriter::registerStdioTargets()} to register both.
     *
     * If environment variable `CONSOLE_TARGET` is `stderr` or `stdout`, output
     * is written to the given target whether the script is running on the
     * command line or not.
     *
     * @param bool $replace If `true`, deregister any targets backed by `STDOUT`
     * or `STDERR` if necessary.
     * @return $this
     */
    public function maybeRegisterStdioTargets(bool $replace = false)
    {
        $output = Env::get('CONSOLE_TARGET', null);

        if ($output !== null) {
            switch (Str::lower($output)) {
                case 'stderr':
                    return $this->registerStdioTarget(
                        $replace,
                        $this->getStderrTarget(),
                        true,
                    );

                case 'stdout':
                    return $this->registerStdioTarget(
                        $replace,
                        $this->getStdoutTarget(),
                        true,
                    );

                default:
                    throw new InvalidEnvironmentException(
                        sprintf('Invalid CONSOLE_TARGET value: %s', $output)
                    );
            }
        }

        if (stream_isatty(\STDERR) && !stream_isatty(\STDOUT)) {
            return $this->registerStderrTarget($replace);
        }

        return $this->registerStdioTargets($replace);
    }

    /**
     * Register STDOUT and STDERR to receive console output if running on the
     * command line and no other standard output targets are registered
     *
     * - Errors and warnings are written to `STDERR`
     * - Informational messages are written to `STDOUT`
     * - {@see Level::DEBUG} messages are suppressed if environment variable
     *   `DEBUG` is empty or not set.
     *
     * @param bool $replace If `true`, deregister any targets backed by `STDOUT`
     * or `STDERR` if necessary.
     * @return $this
     */
    public function registerStdioTargets(bool $replace = false)
    {
        if (
            \PHP_SAPI !== 'cli'
            || ($this->State->StdioTargetsByLevel && !$replace)
        ) {
            return $this;
        }

        $stderr = $this->getStderrTarget();
        $stderrLevels = LevelGroup::ERRORS_AND_WARNINGS;

        $stdout = $this->getStdoutTarget();
        $stdoutLevels = Env::debug()
            ? LevelGroup::INFO
            : LevelGroup::INFO_EXCEPT_DEBUG;

        return $this
            ->clearStdioTargets()
            ->registerTarget($stderr, $stderrLevels)
            ->registerTarget($stdout, $stdoutLevels)
            ->closeDeregisteredTargets();
    }

    /**
     * Register STDERR to receive all console output if running on the command
     * line and no other standard output targets are registered
     *
     * @param bool $replace If `true`, deregister any targets backed by `STDOUT`
     * or `STDERR` if necessary.
     * @return $this
     */
    public function registerStderrTarget(bool $replace = false)
    {
        return $this->registerStdioTarget(
            $replace,
            $this->getStderrTarget(),
        );
    }

    /**
     * Register a STDOUT or STDERR target to receive all output if running on
     * the command line and no other standard output targets are registered
     *
     * @param bool $replace If `true`, deregister any targets backed by `STDOUT`
     * or `STDERR` if necessary.
     * @param bool $force If `true`, register the target even if not running on
     * the command line.
     * @return $this
     */
    private function registerStdioTarget(
        bool $replace,
        Target $target,
        bool $force = false
    ) {
        if (
            !($force || \PHP_SAPI === 'cli')
            || ($this->State->StdioTargetsByLevel && !$replace)
        ) {
            return $this;
        }

        $levels = Env::debug()
            ? LevelGroup::ALL
            : LevelGroup::ALL_EXCEPT_DEBUG;

        return $this
            ->clearStdioTargets()
            ->registerTarget($target, $levels)
            ->closeDeregisteredTargets();
    }

    /**
     * @return $this
     */
    private function clearStdioTargets()
    {
        if (!$this->State->StdioTargetsByLevel) {
            return $this;
        }
        $targets = $this->reduceTargets($this->State->StdioTargetsByLevel);
        foreach ($targets as $target) {
            $this->onlyDeregisterTarget($target);
        }
        return $this;
    }

    /**
     * Close and deregister all registered targets
     *
     * @return $this
     */
    public function deregisterAllTargets()
    {
        foreach ($this->State->Targets as $target) {
            $this->onlyDeregisterTarget($target);
        }
        return $this->closeDeregisteredTargets();
    }

    /**
     * Register a target to receive console output
     *
     * @param array<Level::*> $levels
     * @return $this
     */
    public function registerTarget(
        Target $target,
        array $levels = LevelGroup::ALL
    ) {
        $type = 0;

        if ($target instanceof TargetStream) {
            $type |= TargetTypeFlag::STREAM;

            if ($target->isStdout()) {
                $type |= TargetTypeFlag::STDIO | TargetTypeFlag::STDOUT;
                $this->State->StdoutTarget = $target;
            }

            if ($target->isStderr()) {
                $type |= TargetTypeFlag::STDIO | TargetTypeFlag::STDERR;
                $this->State->StderrTarget = $target;
            }

            if ($type & TargetTypeFlag::STDIO) {
                $targetsByLevel[] = &$this->State->StdioTargetsByLevel;
            }

            if ($target->isTty()) {
                $type |= TargetTypeFlag::TTY;
                $targetsByLevel[] = &$this->State->TtyTargetsByLevel;
            }
        }

        $targetsByLevel[] = &$this->State->TargetsByLevel;

        $targetId = spl_object_id($target);

        foreach ($targetsByLevel as &$targetsByLevel) {
            foreach ($levels as $level) {
                $targetsByLevel[$level][$targetId] = $target;
            }
        }

        $this->State->Targets[$targetId] = $target;
        $this->State->TargetTypeFlags[$targetId] = $type;

        return $this;
    }

    /**
     * Close and deregister a previously registered target
     *
     * @return $this
     */
    public function deregisterTarget(Target $target)
    {
        return $this->onlyDeregisterTarget($target)->closeDeregisteredTargets();
    }

    /**
     * @return $this
     */
    private function onlyDeregisterTarget(Target $target)
    {
        $targetId = spl_object_id($target);

        unset($this->State->Targets[$targetId]);

        foreach ([
            &$this->State->TargetsByLevel,
            &$this->State->TtyTargetsByLevel,
            &$this->State->StdioTargetsByLevel,
        ] as &$targetsByLevel) {
            foreach ($targetsByLevel as $level => &$targets) {
                unset($targets[$targetId]);
                if (!$targets) {
                    unset($targetsByLevel[$level]);
                }
            }
        }

        if ($this->State->StderrTarget === $target) {
            $this->State->StderrTarget = null;
        }

        if ($this->State->StdoutTarget === $target) {
            $this->State->StdoutTarget = null;
        }

        $this->State->DeregisteredTargets[$targetId] = $target;

        // Reinstate previous STDOUT and STDERR targets if possible
        if (
            $this->State->Targets
            && (!$this->State->StdoutTarget || !$this->State->StderrTarget)
        ) {
            foreach (array_reverse($this->State->Targets) as $target) {
                if (!$target instanceof TargetStream) {
                    continue;
                }
                if (!$this->State->StdoutTarget && $target->isStdout()) {
                    $this->State->StdoutTarget = $target;
                    if ($this->State->StderrTarget) {
                        break;
                    }
                }
                if (!$this->State->StderrTarget && $target->isStderr()) {
                    $this->State->StderrTarget = $target;
                    if ($this->State->StdoutTarget) {
                        break;
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function closeDeregisteredTargets()
    {
        // Reduce `$this->State->DeregisteredTargets` to targets not
        // subsequently re-registered
        $this->State->DeregisteredTargets = array_diff_key(
            $this->State->DeregisteredTargets, $this->State->Targets
        );
        foreach ($this->State->DeregisteredTargets as $i => $target) {
            $target->close();
            unset($this->State->DeregisteredTargets[$i]);
        }
        return $this;
    }

    /**
     * Get a list of registered targets
     *
     * @return Target[]
     */
    public function getTargets(): array
    {
        return array_values($this->State->Targets);
    }

    /**
     * Set or unset the prefix applied to each line of output by targets that
     * implement ConsoleTargetPrefixInterface
     *
     * @param int-mask-of<TargetTypeFlag::*> $flags
     * @return $this
     */
    public function setTargetPrefix(?string $prefix, int $flags = 0)
    {
        $invertFlags = false;
        if ($flags & TargetTypeFlag::INVERT) {
            $flags &= ~TargetTypeFlag::INVERT;
            $invertFlags = true;
        }

        foreach ($this->State->Targets as $targetId => $target) {
            if (!$target instanceof TargetPrefix || (
                $flags
                && !($this->State->TargetTypeFlags[$targetId] & $flags xor $invertFlags)
            )) {
                continue;
            }
            $target->setPrefix($prefix);
        }

        return $this;
    }

    /**
     * Get the width of a registered target in columns
     *
     * Returns {@see Target::getWidth()} from whichever is found first:
     *
     * - the first TTY target registered with the given level
     * - the first `STDOUT` or `STDERR` target registered with the given level
     * - the first target registered with the given level
     * - the target returned by {@see getStderrTarget()} if backed by a TTY
     * - the target returned by {@see getStdoutTarget()}
     *
     * @param Level::* $level
     */
    public function getWidth($level = Level::INFO): ?int
    {
        return $this->maybeGetTtyTarget($level)->getWidth();
    }

    /**
     * Get an output formatter for a registered target
     *
     * Returns {@see Target::getFormatter()} from the same target as
     * {@see ConsoleWriter::getWidth()}.
     *
     * @param Level::* $level
     */
    public function getFormatter($level = Level::INFO): Formatter
    {
        return $this->maybeGetTtyTarget($level)->getFormatter();
    }

    /**
     * @param Level::* $level
     */
    private function maybeGetTtyTarget($level): TargetStream
    {
        /** @var Target[] */
        $targets = $this->State->TtyTargetsByLevel[$level]
            ?? $this->State->StdioTargetsByLevel[$level]
            ?? $this->State->TargetsByLevel[$level]
            ?? [];

        $target = reset($targets);
        if (!$target || !$target instanceof TargetStream) {
            $target = $this->getStderrTarget();
            if (!$target->isTty()) {
                return $this->getStdoutTarget();
            }
        }

        return $target;
    }

    /**
     * Get a target for STDOUT, creating it if necessary
     */
    public function getStdoutTarget(): TargetStream
    {
        if (!$this->State->StdoutTarget) {
            return $this->State->StdoutTarget = StreamTarget::fromStream(\STDOUT);
        }
        return $this->State->StdoutTarget;
    }

    /**
     * Get a target for STDERR, creating it if necessary
     */
    public function getStderrTarget(): TargetStream
    {
        if (!$this->State->StderrTarget) {
            return $this->State->StderrTarget = StreamTarget::fromStream(\STDERR);
        }
        return $this->State->StderrTarget;
    }

    /**
     * Get the number of errors reported so far
     */
    public function getErrors(): int
    {
        return $this->State->Errors;
    }

    /**
     * Get the number of warnings reported so far
     */
    public function getWarnings(): int
    {
        return $this->State->Warnings;
    }

    /**
     * Print a "command finished" message with a summary of errors and warnings
     *
     * Prints `" // $finishedText $successText"` with level INFO if no errors or
     * warnings have been reported (default: `" // Command finished without
     * errors"`).
     *
     * Otherwise, prints one of the following with level ERROR or WARNING:
     * - `" !! $finishedText with $errors errors[ and $warnings warnings]"`
     * - `"  ! $finishedText with 0 errors and $warnings warnings"`
     *
     * @return $this
     */
    public function summary(
        string $finishedText = 'Command finished',
        string $successText = 'without errors',
        bool $withResourceUsage = false
    ) {
        if ($withResourceUsage) {
            $usage = sprintf(
                'in %.3fs (%s memory used)',
                microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                Format::bytes(Sys::getPeakMemoryUsage()),
            );
        }

        $msg1 = trim($finishedText);
        if ($this->State->Errors + $this->State->Warnings === 0) {
            return $this->write(
                Level::INFO,
                Arr::implode(' ', [$msg1, $successText, $usage ?? null]),
                null,
                MessageType::SUCCESS
            );
        }

        $msg2 = 'with ' . Inflect::format($this->State->Errors, '{{#}} {{#:error}}');
        if ($this->State->Warnings) {
            $msg2 .= ' and ' . Inflect::format($this->State->Warnings, '{{#}} {{#:warning}}');
        }

        return $this->write(
            $this->State->Errors ? Level::ERROR : Level::WARNING,
            Arr::implode(' ', [$msg1, $msg2, $usage ?? null]),
            null
        );
    }

    /**
     * Print "$msg"
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function print(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->State->TargetsByLevel);
    }

    /**
     * Print "$msg" to I/O stream targets (STDOUT or STDERR)
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function out(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->State->StdioTargetsByLevel);
    }

    /**
     * Print "$msg" to TTY targets
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function tty(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->State->TtyTargetsByLevel);
    }

    /**
     * Print "$msg" to STDOUT, creating a target for it if necessary
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function stdout(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNFORMATTED
    ) {
        $targets = [$level => [$this->getStdoutTarget()]];
        $this->_write($level, $msg, null, $type, null, $targets);

        return $this;
    }

    /**
     * Print "$msg" to STDERR, creating a target for it if necessary
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function stderr(
        string $msg,
        $level = Level::INFO,
        $type = MessageType::UNFORMATTED
    ) {
        $targets = [$level => [$this->getStderrTarget()]];
        $this->_write($level, $msg, null, $type, null, $targets);

        return $this;
    }

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     *
     * This method increments the message counter for `$level`.
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function message(
        $level,
        string $msg1,
        ?string $msg2 = null,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        if ($count) {
            $this->count($level);
        }

        return $this->write($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     * once per run
     *
     * This method increments the message counter for `$level`.
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    public function messageOnce(
        $level,
        string $msg1,
        ?string $msg2 = null,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        if ($count) {
            $this->count($level);
        }

        return $this->writeOnce($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * Increment the message counter for $level without printing anything
     *
     * @param Level::* $level
     * @return $this
     */
    public function count($level)
    {
        switch ($level) {
            case Level::EMERGENCY:
            case Level::ALERT:
            case Level::CRITICAL:
            case Level::ERROR:
                $this->State->Errors++;
                break;

            case Level::WARNING:
                $this->State->Warnings++;
                break;
        }

        return $this;
    }

    /**
     * Print " !! $msg1 $msg2" with level ERROR
     *
     * @return $this
     */
    public function error(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->State->Errors++;

        return $this->write(Level::ERROR, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print " !! $msg1 $msg2" with level ERROR once per run
     *
     * @return $this
     */
    public function errorOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->State->Errors++;

        return $this->writeOnce(Level::ERROR, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING
     *
     * @return $this
     */
    public function warn(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->State->Warnings++;

        return $this->write(Level::WARNING, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING once per run
     *
     * @return $this
     */
    public function warnOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->State->Warnings++;

        return $this->writeOnce(Level::WARNING, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE
     *
     * @return $this
     */
    public function info(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->write(Level::NOTICE, $msg1, $msg2);
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE once per run
     *
     * @return $this
     */
    public function infoOnce(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->writeOnce(Level::NOTICE, $msg1, $msg2);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO
     *
     * @return $this
     */
    public function log(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->write(Level::INFO, $msg1, $msg2);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO once per run
     *
     * @return $this
     */
    public function logOnce(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->writeOnce(Level::INFO, $msg1, $msg2);
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO to TTY targets without moving to
     * the next line
     *
     * The next message sent to TTY targets is written with a leading "clear to
     * end of line" sequence unless {@see maybeClearLine()} has been called in
     * the meantime.
     *
     * {@see logProgress()} can be called repeatedly to display transient
     * progress updates when running interactively, without disrupting other
     * console messages or bloating output logs.
     *
     * @return $this
     */
    public function logProgress(
        string $msg1,
        ?string $msg2 = null
    ) {
        if (!($this->State->TtyTargetsByLevel[Level::INFO] ?? null)) {
            return $this;
        }

        if ($msg2 === null || $msg2 === '') {
            $msg1 = rtrim($msg1, "\r") . "\r";
        } else {
            $msg2 = rtrim($msg2, "\r") . "\r";
        }

        return $this->writeTty(Level::INFO, $msg1, $msg2);
    }

    /**
     * Print a "clear to end of line" control sequence with level INFO to any
     * TTY targets with a pending logProgress() message
     *
     * Useful when progress updates that would disrupt other output to STDOUT or
     * STDERR may have been displayed.
     *
     * @return $this
     */
    public function maybeClearLine()
    {
        if (!($this->State->TtyTargetsByLevel[Level::INFO] ?? null)) {
            return $this;
        }

        return $this->writeTty(Level::INFO, "\r", null, MessageType::UNFORMATTED);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG
     *
     * @param int $depth Passed to {@see Debug::getCaller()}. To print your
     * caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    public function debug(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        int $depth = 0
    ) {
        if ($this->Facade !== null) {
            $depth++;
        }

        $caller = implode('', Debug::getCaller($depth));
        $msg1 = $msg1 ? ' __' . $msg1 . '__' : '';

        return $this->write(Level::DEBUG, "{{$caller}}{$msg1}", $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run
     *
     * @param int $depth Passed to {@see Debug::getCaller()}. To print your
     * caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    public function debugOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        int $depth = 0
    ) {
        if ($this->Facade !== null) {
            $depth++;
        }

        $caller = implode('', Debug::getCaller($depth));

        return $this->writeOnce(Level::DEBUG, "{{$caller}} __" . $msg1 . '__', $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * Create a new message group and print "<<< $msg1 $msg2" with level NOTICE
     *
     * The message group will remain open, and subsequent messages will be
     * indented, until {@see groupEnd()} is called.
     *
     * @return $this
     */
    public function group(
        string $msg1,
        ?string $msg2 = null
    ) {
        $this->State->GroupLevel++;
        $this->State->GroupMessageStack[] = Arr::implode(' ', [$msg1, $msg2]);

        return $this->write(Level::NOTICE, $msg1, $msg2, MessageType::GROUP_START);
    }

    /**
     * Close the most recently created message group
     *
     * @return $this
     *
     * @see ConsoleWriter::group()
     */
    public function groupEnd(bool $printMessage = false)
    {
        $msg = array_pop($this->State->GroupMessageStack);
        if ($printMessage
                && $msg !== null
                && $msg !== ''
                && ($msg = Formatter::removeTags($msg)) !== '') {
            $this->write(Level::NOTICE, '', $msg ? "{ $msg } complete" : null, MessageType::GROUP_END);
        }
        $this->out('', Level::NOTICE);

        if ($this->State->GroupLevel > -1) {
            $this->State->GroupLevel--;
        }

        return $this;
    }

    /**
     * Report an uncaught exception
     *
     * Prints `" !! <exception>: <message> in <file>:<line>"` with level
     * `$messageLevel` (default: ERROR), followed by the exception's stack trace
     * with level `$stackTraceLevel` (default: DEBUG).
     *
     * @param Level::* $messageLevel
     * @param Level::*|null $stackTraceLevel If `null`, the exception's stack
     * trace is not printed.
     * @return $this
     */
    public function exception(
        Throwable $exception,
        $messageLevel = Level::ERROR,
        $stackTraceLevel = Level::DEBUG
    ) {
        $ex = $exception;
        $msg2 = '';
        $i = 0;
        do {
            if ($i++) {
                $class = Formatter::escapeTags(Get::basename(get_class($ex)));
                $msg2 .= sprintf("\nCaused by __%s__: ", $class);
            }

            if ($ex instanceof MultipleErrorExceptionInterface
                    && !$ex->hasUnreportedErrors()) {
                $message = Formatter::escapeTags($ex->getMessageWithoutErrors());
            } else {
                $message = Formatter::escapeTags($ex->getMessage());
            }

            $file = Formatter::escapeTags($ex->getFile());
            $line = $ex->getLine();
            $msg2 .= sprintf('%s ~~in %s:%d~~', $message, $file, $line);

            $ex = $ex->getPrevious();
        } while ($ex);

        $class = Formatter::escapeTags(Get::basename(get_class($exception)));
        $this->count($messageLevel)->write(
            $messageLevel,
            sprintf('__%s__:', $class),
            $msg2,
            MessageType::STANDARD,
            $exception,
            true
        );
        if ($stackTraceLevel === null) {
            return $this;
        }
        $this->write(
            $stackTraceLevel,
            '__Stack trace:__',
            "\n" . $exception->getTraceAsString()
        );
        if ($exception instanceof ExceptionInterface) {
            foreach ($exception->getMetadata() as $key => $value) {
                $value = rtrim((string) $value, "\n");
                $this->write($stackTraceLevel, "__{$key}:__", "\n{$value}");
            }
        }

        return $this;
    }

    /**
     * @param array<Level::*,Target[]> $targets
     * @return Target[]
     */
    private function reduceTargets(array $targets): array
    {
        foreach ($targets as $levelTargets) {
            foreach ($levelTargets as $target) {
                $targetId = spl_object_id($target);
                $reduced[$targetId] = $target;
            }
        }
        return $reduced ?? [];
    }

    /**
     * Send a message to registered targets
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function write(
        $level,
        string $msg1,
        ?string $msg2,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TargetsByLevel, $msg2HasTags);
    }

    /**
     * Send a message to registered targets once per run
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function writeOnce(
        $level,
        string $msg1,
        ?string $msg2,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        $hash = Get::hash(implode("\0", [$level, $msg1, $msg2, $type, $msg2HasTags]));
        if ($this->State->Written[$hash] ?? false) {
            return $this;
        }
        $this->State->Written[$hash] = true;
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TargetsByLevel, $msg2HasTags);
    }

    /**
     * Send a message to registered TTY targets
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function writeTty(
        $level,
        string $msg1,
        ?string $msg2,
        $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TtyTargetsByLevel, $msg2HasTags);
    }

    /**
     * @param Level::* $level
     * @param MessageType::* $type
     * @param array<Level::*,Target[]> $targets
     * @return $this
     */
    private function _write(
        $level,
        string $msg1,
        ?string $msg2,
        $type,
        ?Throwable $ex,
        array &$targets,
        bool $msg2HasTags = false
    ) {
        if (!$this->State->Targets) {
            $this->registerLogTarget();
            $this->maybeRegisterStdioTargets();
        }

        // As per PSR-3 Section 1.3
        if ($ex) {
            $context['exception'] = $ex;
        }

        $margin = max(0, $this->State->GroupLevel * 4);

        foreach ($targets[$level] ?? [] as $target) {
            $formatter = $target->getFormatter();

            $indent = strlen($formatter->getMessagePrefix($level, $type));
            $indent = max(0, strpos($msg1, "\n") !== false ? $indent : $indent - 4);

            $_msg1 = $msg1 === '' ? '' : $formatter->formatTags($msg1);

            if ($margin + $indent > 0 && strpos($msg1, "\n") !== false) {
                $_msg1 = str_replace("\n", "\n" . str_repeat(' ', $margin + $indent), $_msg1);
            }

            $_msg2 = null;
            if ($msg2 !== null && $msg2 !== '') {
                $_msg2 = $msg2HasTags ? $formatter->formatTags($msg2) : $msg2;
                $_msg2 = strpos($msg2, "\n") !== false
                    ? str_replace("\n", "\n" . str_repeat(' ', $margin + $indent + 2), "\n" . ltrim($_msg2))
                    : ($_msg1 !== '' ? ' ' : '') . $_msg2;
            }

            $message = $formatter->formatMessage($_msg1, $_msg2, $level, $type);
            $target->write($level, str_repeat(' ', $margin) . $message, $context ?? []);
        }

        return $this;
    }
}
