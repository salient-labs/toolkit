<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Console\Catalog\ConsoleLevels as Levels;
use Lkrms\Console\Catalog\ConsoleMessageType as Type;
use Lkrms\Console\ConsoleFormatter as Formatter;
use Lkrms\Console\Contract\IConsoleTarget as Target;
use Lkrms\Console\Contract\IConsoleTargetWithOptionalPrefix as TargetWithPrefix;
use Lkrms\Console\Target\StreamTarget;
use Lkrms\Contract\ReceivesFacade;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Debug;
use Lkrms\Facade\File;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Env;
use Throwable;
use UnexpectedValueException;

/**
 * Log messages of various types to various targets
 *
 * Access via the {@see \Lkrms\Facade\Console} facade is recommended.
 *
 */
final class ConsoleWriter implements ReceivesFacade
{
    /**
     * @var array<Level::*,Target[]>
     */
    private array $StdioTargetsByLevel = [];

    /**
     * @var array<Level::*,Target[]>
     */
    private array $TtyTargetsByLevel = [];

    /**
     * @var array<Level::*,Target[]>
     */
    private array $TargetsByLevel = [];

    /**
     * @var Target[]
     */
    private array $Targets = [];

    private ?Target $StdoutTarget = null;

    private ?Target $StderrTarget = null;

    private int $GroupLevel = -1;

    /**
     * @var string[]
     */
    private array $GroupMessageStack = [];

    private int $Errors = 0;

    private int $Warnings = 0;

    /**
     * Message hash => counter
     *
     * @var array<string,int>
     */
    private array $Written = [];

    private ?string $Facade = null;

    public function setFacade(string $name)
    {
        $this->Facade = $name;
        return $this;
    }

    /**
     * Register the default output log as a target for all console messages
     *
     * Console output is appended to a file in the default temporary directory,
     * created with mode 0600 if it doesn't already exist:
     *
     * ```php
     * sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
     * ```
     *
     * @return $this
     */
    public function registerDefaultOutputLog()
    {
        return $this->registerTarget(
            StreamTarget::fromPath(File::getStablePath('.log')),
            Levels::ALL
        );
    }

    /**
     * Register STDOUT and STDERR as targets in their default configuration
     *
     * If the value of environment variable `CONSOLE_OUTPUT` is `stderr` or
     * `stdout`, console output is written to `STDERR` or `STDOUT` respectively.
     *
     * Otherwise, when running on the command line:
     *
     * - If `STDERR` is a TTY and `STDOUT` is not, console messages are written
     *   to `STDERR` so output to `STDOUT` isn't tainted
     * - Otherwise, errors and warnings are written to `STDERR`, and
     *   informational messages are written to `STDOUT`
     *
     * Debug messages are suppressed if environment variable `DEBUG` is empty or
     * not set.
     *
     * @param bool $replace If `false` (the default) and a target backed by
     * `STDOUT` or `STDERR` has already been registered, no action is taken.
     * @return $this
     */
    public function registerDefaultStdioTargets(bool $replace = false)
    {
        $output = Env::get('CONSOLE_OUTPUT', null);

        if ($output !== null) {
            switch (strtolower($output)) {
                case 'stderr':
                    return $this->registerStdioTarget($replace, STDERR, true);

                case 'stdout':
                    return $this->registerStdioTarget($replace, STDOUT, true);

                default:
                    throw new UnexpectedValueException(
                        sprintf('Invalid CONSOLE_OUTPUT value: %s', $output)
                    );
            }
        }

        if (stream_isatty(STDERR) && !stream_isatty(STDOUT)) {
            return $this->registerStderrTarget($replace);
        }

        return $this->registerStdioTargets($replace);
    }

    /**
     * Register STDOUT and STDERR as targets if running on the command line
     *
     * Errors and warnings are written to `STDERR`, informational messages are
     * written to `STDOUT`, and debug messages are suppressed if environment
     * variable `DEBUG` is empty or not set.
     *
     * @param bool $replace If `false` (the default) and a target backed by
     * `STDOUT` or `STDERR` has already been registered, no action is taken.
     * @return $this
     */
    public function registerStdioTargets(bool $replace = false)
    {
        if (PHP_SAPI !== 'cli' || ($this->StdioTargetsByLevel && !$replace)) {
            return $this;
        }

        $stderrLevels = Levels::ERRORS_AND_WARNINGS;
        $stdoutLevels = Env::debug()
            ? Levels::INFO
            : Levels::INFO_EXCEPT_DEBUG;

        return $this
            ->clearStdioTargets()
            ->registerTarget(new StreamTarget(STDERR), $stderrLevels)
            ->registerTarget(new StreamTarget(STDOUT), $stdoutLevels);
    }

    /**
     * Register STDERR as a target for all console messages if running on the
     * command line
     *
     * @param bool $replace If `false` (the default) and a target backed by
     * `STDOUT` or `STDERR` has already been registered, no action is taken.
     * @return $this
     */
    public function registerStderrTarget(bool $replace = false)
    {
        return $this->registerStdioTarget($replace, STDERR);
    }

    /**
     * Register a target to receive one or more levels of console messages
     *
     * @param array<Level::*> $levels
     * @return $this
     */
    public function registerTarget(
        Target $target,
        array $levels = Levels::ALL
    ) {
        if ($target->isStdout()) {
            $this->StdoutTarget = $target;
            $isStdio = true;
        }

        if ($target->isStderr()) {
            $this->StderrTarget = $target;
            $isStdio = true;
        }

        if ($isStdio ?? null) {
            $targetsByLevel[] = &$this->StdioTargetsByLevel;
        }

        if ($target->isTty()) {
            $targetsByLevel[] = &$this->TtyTargetsByLevel;
        }

        $targetsByLevel[] = &$this->TargetsByLevel;

        $targetId = spl_object_id($target);

        foreach ($targetsByLevel as &$targetsByLevel) {
            foreach ($levels as $level) {
                $targetsByLevel[$level][$targetId] = $target;
            }
        }

        $this->Targets[$targetId] = $target;

        return $this;
    }

    /**
     * Deregister a previously registered target
     *
     * @return $this
     */
    public function deregisterTarget(Target $target)
    {
        $targetId = spl_object_id($target);

        unset($this->Targets[$targetId]);

        foreach ([
            &$this->TargetsByLevel,
            &$this->TtyTargetsByLevel,
            &$this->StdioTargetsByLevel,
        ] as &$targetsByLevel) {
            foreach ($targetsByLevel as $level => &$targets) {
                unset($targets[$targetId]);
                if (!$targets) {
                    unset($targetsByLevel[$level]);
                }
            }
        }

        if ($this->StderrTarget === $target) {
            $this->StderrTarget = null;
        }

        if ($this->StdoutTarget === $target) {
            $this->StdoutTarget = null;
        }

        return $this;
    }

    /**
     * Call setPrefix on registered targets
     *
     * `$prefix` is applied to all registered targets by default. Set `$stdio`
     * to `false` to exclude targets backed by STDOUT or STDERR, or set
     * `$notStdio` to `false` to exclude targets other than STDOUT and STDERR.
     *
     * Default targets are registered if this method is called before an
     * explicit target has been registered.
     *
     * @param bool $ttyOnly If `true`, only call
     * {@see TargetWithPrefix::setPrefix()} on TTY targets. `$stdio` and/or
     * `$notStdio` must also be `true`.
     * @return $this
     */
    public function setTargetPrefix(
        ?string $prefix,
        bool $ttyOnly = false,
        bool $stdio = true,
        bool $notStdio = true
    ) {
        if (!$this->Targets) {
            $this->registerDefaultTargets();
        }

        foreach ($this->Targets as $target) {
            if (!($target instanceof TargetWithPrefix) ||
                    ($ttyOnly && !$target->isTty()) ||
                    (!$stdio && ($target->isStdout() || $target->isStderr())) ||
                    (!$notStdio && !($target->isStdout() || $target->isStderr()))) {
                continue;
            }
            $target->setPrefix($prefix);
        }

        return $this;
    }

    /**
     * Get the number of errors reported so far
     *
     */
    public function getErrors(): int
    {
        return $this->Errors;
    }

    /**
     * Get the number of warnings reported so far
     *
     */
    public function getWarnings(): int
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
    public function summary(
        string $finishedText = 'Command finished',
        string $successText = 'without errors'
    ) {
        $msg1 = trim($finishedText);
        if (0 === $this->Warnings + $this->Errors) {
            return $this->write(Level::INFO, "$msg1 $successText", null, Type::SUCCESS);
        }

        $msg2 = 'with ' . Convert::plural($this->Errors, 'error', 'errors', true);
        if ($this->Warnings) {
            $msg2 .= ' and ' . Convert::plural($this->Warnings, 'warning', 'warnings', true);
        }

        return $this->write(
            $this->Errors ? Level::ERROR : Level::WARNING,
            "$msg1 $msg2",
            null
        );
    }

    /**
     * Print "$msg"
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    public function print(
        string $msg,
        $level = Level::INFO,
        $type = Type::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->TargetsByLevel);
    }

    /**
     * Print "$msg" to I/O stream targets (STDOUT or STDERR)
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    public function out(
        string $msg,
        $level = Level::INFO,
        $type = Type::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->StdioTargetsByLevel);
    }

    /**
     * Print "$msg" to TTY targets
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    public function tty(
        string $msg,
        $level = Level::INFO,
        $type = Type::UNDECORATED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->TtyTargetsByLevel);
    }

    /**
     * Print "$msg" to STDOUT, creating a target for it if necessary
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    public function stdout(
        string $msg,
        $level = Level::INFO,
        $type = Type::UNFORMATTED
    ) {
        if (!$this->StdoutTarget) {
            $this->StdoutTarget = new StreamTarget(STDOUT);
        }
        $targets = [$level => [$this->StdoutTarget]];
        $this->_write($level, $msg, null, $type, null, $targets);

        return $this;
    }

    /**
     * Print "$msg" to STDERR, creating a target for it if necessary
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    public function stderr(
        string $msg,
        $level = Level::INFO,
        $type = Type::UNFORMATTED
    ) {
        if (!$this->StderrTarget) {
            $this->StderrTarget = new StreamTarget(STDERR);
        }
        $targets = [$level => [$this->StderrTarget]];
        $this->_write($level, $msg, null, $type, null, $targets);

        return $this;
    }

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     *
     * This method increments the message counter for `$level`.
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    public function message(
        $level,
        string $msg1,
        ?string $msg2 = null,
        $type = Type::DEFAULT,
        ?Throwable $ex = null
    ) {
        return $this
            ->count($level)
            ->write($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     * once per run
     *
     * This method increments the message counter for `$level`.
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    public function messageOnce(
        $level,
        string $msg1,
        ?string $msg2 = null,
        $type = Type::DEFAULT,
        ?Throwable $ex = null
    ) {
        return $this
            ->count($level)
            ->writeOnce($level, $msg1, $msg2, $type, $ex);
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
                $this->Errors++;
                break;

            case Level::WARNING:
                $this->Warnings++;
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
        !$count || $this->Errors++;

        return $this->write(Level::ERROR, $msg1, $msg2, Type::DEFAULT, $ex);
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
        !$count || $this->Errors++;

        return $this->writeOnce(Level::ERROR, $msg1, $msg2, Type::DEFAULT, $ex);
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
        !$count || $this->Warnings++;

        return $this->write(Level::WARNING, $msg1, $msg2, Type::DEFAULT, $ex);
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
        !$count || $this->Warnings++;

        return $this->writeOnce(Level::WARNING, $msg1, $msg2, Type::DEFAULT, $ex);
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
     * The next message sent to the same targets is written with a leading
     * "clear to end of line" sequence unless
     * {@see ConsoleWriter::maybeClearLine()} has been called in the meantime.
     *
     * {@see ConsoleWriter::logProgress()} can be called repeatedly to display
     * transient progress updates only when running interactively, without
     * disrupting other Console messages or bloating output logs.
     *
     * To prevent moving to the next line when message length exceeds terminal
     * width, truncate output by setting `$withLineWrapOff`.
     *
     * @return $this
     */
    public function logProgress(
        string $msg1,
        ?string $msg2 = null
    ) {
        if (!($this->TtyTargetsByLevel[Level::INFO] ?? null)) {
            return $this;
        }

        if (is_null($msg2)) {
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
        if (!($this->TtyTargetsByLevel[Level::INFO] ?? null)) {
            return $this;
        }

        return $this->writeTty(Level::INFO, "\r", null, Type::UNFORMATTED);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG
     *
     * @param int $depth Passed to {@see \Lkrms\Utility\Debugging::getCaller()}.
     * To print your caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    public function debug(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        int $depth = 0
    ) {
        if ($this->Facade) {
            $depth++;
        }

        $caller = implode('', Debug::getCaller($depth));
        $msg1 = $msg1 ? ' __' . $msg1 . '__' : '';

        return $this->write(Level::DEBUG, "{{$caller}}{$msg1}", $msg2, Type::DEFAULT, $ex);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run
     *
     * @param int $depth Passed to {@see \Lkrms\Utility\Debugging::getCaller()}.
     * To print your caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    public function debugOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        int $depth = 0
    ) {
        if ($this->Facade) {
            $depth++;
        }

        $caller = implode('', Debug::getCaller($depth));

        return $this->writeOnce(Level::DEBUG, "{{$caller}} __" . $msg1 . '__', $msg2, Type::DEFAULT, $ex);
    }

    /**
     * Create a new message group and print "<<< $msg1 $msg2" with level NOTICE
     *
     * The message group will remain open, and subsequent messages will be
     * indented, until {@see ConsoleWriter::groupEnd()} is called.
     *
     * @return $this
     */
    public function group(
        string $msg1,
        ?string $msg2 = null
    ) {
        $this->GroupLevel++;
        $this->GroupMessageStack[] = Convert::sparseToString(' ', [$msg1, $msg2]);

        return $this->write(Level::NOTICE, $msg1, $msg2);
    }

    /**
     * Close the most recently created message group
     *
     * @return $this
     * @see ConsoleWriter::group()
     */
    public function groupEnd(bool $printMessage = false)
    {
        if (($msg = array_pop($this->GroupMessageStack)) && $printMessage) {
            $msg = Formatter::removeTags($msg);
            $this->write(Level::NOTICE, '', $msg ? "{ $msg } complete" : null);
        }
        $this->out('', Level::NOTICE);

        if ($this->GroupLevel > -1) {
            $this->GroupLevel--;
        }

        return $this;
    }

    /**
     * Report an uncaught exception
     *
     * Prints " !! <exception>: <message> in <file>:<line>" with level
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
                $class = Formatter::escapeTags(Convert::classToBasename(get_class($ex)));
                $msg2 .= sprintf("\nCaused by __%s__: ", $class);
            }

            $message = Formatter::escapeTags($ex->getMessage());
            $file = Formatter::escapeTags($ex->getFile());
            $line = $ex->getLine();
            $msg2 .= sprintf('%s ~~in %s:%d~~', $message, $file, $line);

            $ex = $ex->getPrevious();
        } while ($ex);

        $class = Formatter::escapeTags(Convert::classToBasename(get_class($exception)));
        $this->count($messageLevel)->write(
            $messageLevel,
            sprintf('__%s__:', $class),
            $msg2,
            Type::DEFAULT,
            $exception,
            true
        );
        if (is_null($stackTraceLevel)) {
            return $this;
        }
        $this->write(
            $stackTraceLevel,
            '__Stack trace:__',
            "\n" . $exception->getTraceAsString()
        );
        if ($exception instanceof \Lkrms\Exception\Exception) {
            foreach ($exception->getDetail() as $section => $text) {
                $this->write(
                    $stackTraceLevel,
                    "__{$section}:__",
                    "\n{$text}"
                );
            }
        }

        return $this;
    }

    private function registerDefaultTargets(): void
    {
        $this->registerDefaultOutputLog();
        $this->registerDefaultStdioTargets();
    }

    /**
     * @param resource $stream
     * @param bool $force If `true`, the target is registered even if not
     * running on the command line.
     * @return $this
     */
    private function registerStdioTarget(bool $replace, $stream, bool $force = false)
    {
        if (!($force || PHP_SAPI === 'cli') ||
                ($this->StdioTargetsByLevel && !$replace)) {
            return $this;
        }

        $levels = Env::debug()
            ? Levels::ALL
            : Levels::ALL_EXCEPT_DEBUG;

        return $this
            ->clearStdioTargets()
            ->registerTarget(new StreamTarget($stream), $levels);
    }

    /**
     * @return $this
     */
    private function clearStdioTargets()
    {
        if (!$this->StdioTargetsByLevel) {
            return $this;
        }
        $targets = $this->reduceTargets($this->StdioTargetsByLevel);
        foreach ($targets as $target) {
            $this->deregisterTarget($target);
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
     * Send a message to registered targets once per run
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    private function writeOnce(
        $level,
        string $msg1,
        ?string $msg2,
        $type = Type::DEFAULT,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        $hash = Compute::hash($level, $msg1, $msg2, $type, $msg2HasTags);
        if (($this->Written[$hash] = ($this->Written[$hash] ?? 0) + 1) < 2) {
            return $this->write($level, $msg1, $msg2, $type, $ex, $msg2HasTags);
        }
        return $this;
    }

    /**
     * Send a message to registered targets
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    private function write(
        $level,
        string $msg1,
        ?string $msg2,
        $type = Type::DEFAULT,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->TargetsByLevel, $msg2HasTags);
    }

    /**
     * Send a message to registered TTY targets
     *
     * @param Level::* $level
     * @param Type::* $type
     * @return $this
     */
    private function writeTty(
        $level,
        string $msg1,
        ?string $msg2,
        $type = Type::DEFAULT,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->TtyTargetsByLevel, $msg2HasTags);
    }

    /**
     * @param Level::* $level
     * @param Type::* $type
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
        if (!$this->Targets) {
            $this->registerDefaultTargets();
        }

        // As per PSR-3 Section 1.3
        if ($ex) {
            $context['exception'] = $ex;
        }

        $margin = max(0, $this->GroupLevel * 4);

        /** @var Target $target */
        foreach ($targets[$level] ?? [] as $target) {
            $formatter = $target->getFormatter();

            $indent = strlen($formatter->getMessagePrefix($level, $type));
            $indent = max(0, strpos($msg1, "\n") !== false ? $indent : $indent - 4);

            $_msg1 = $msg1 === '' ? '' : $formatter->formatTags($msg1);

            if ($margin + $indent > 0 && strpos($msg1, "\n") !== false) {
                $_msg1 = str_replace("\n", "\n" . str_repeat(' ', $margin + $indent), $_msg1);
            }

            if (($msg2 ?? '') !== '') {
                $_msg2 = $msg2HasTags ? $formatter->formatTags($msg2) : $msg2;
                $_msg2 = strpos($msg2, "\n") !== false
                    ? str_replace("\n", "\n" . str_repeat(' ', $margin + $indent + 2), "\n" . ltrim($_msg2))
                    : ($_msg1 !== '' ? ' ' : '') . $_msg2;
            }

            $message = $formatter->formatMessage($_msg1, $_msg2 ?? null, $level, $type);
            $target->write($level, str_repeat(' ', $margin) . $message, $context ?? []);
        }

        return $this;
    }
}
