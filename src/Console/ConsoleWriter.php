<?php declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\Concept\ConsoleTarget;
use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Console\Target\StreamTarget;
use Lkrms\Contract\ReceivesFacade;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Debug;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use RuntimeException;
use Throwable;

/**
 * Log messages of various types to various targets
 *
 * Typically accessed via the {@see \Lkrms\Facade\Console} facade.
 *
 */
final class ConsoleWriter implements ReceivesFacade
{
    private const LEVEL_PREFIX = [
        Level::EMERGENCY => ' !! ',
        Level::ALERT => ' !! ',
        Level::CRITICAL => ' !! ',
        Level::ERROR => ' !! ',
        Level::WARNING => '  ! ',
        Level::NOTICE => '==> ',
        Level::INFO => ' -> ',
        Level::DEBUG => '--- ',
    ];

    /**
     * Message level => ConsoleTarget[]
     *
     * @var array<int,ConsoleTarget[]>
     */
    private $StdioTargets = [];

    /**
     * Message level => ConsoleTarget[]
     *
     * @var array<int,ConsoleTarget[]>
     */
    private $ExceptStdioTargets = [];

    /**
     * Message level => ConsoleTarget[]
     *
     * @var array<int,ConsoleTarget[]>
     */
    private $TtyTargets = [];

    /**
     * Message level => ConsoleTarget[]
     *
     * @var array<int,ConsoleTarget[]>
     */
    private $Targets = [];

    /**
     * @var int
     */
    private $GroupLevel = -1;

    /**
     * @var string[]
     */
    private $GroupMessageStack = [];

    /**
     * @var int
     */
    private $Errors = 0;

    /**
     * @var int
     */
    private $Warnings = 0;

    /**
     * Message hash => counter
     *
     * @var array<string,int>
     */
    private $Written = [];

    /**
     * @var string|null
     */
    private $Facade;

    /**
     * @return $this
     */
    public function registerTarget(ConsoleTarget $target, array $levels = ConsoleLevels::ALL_DEBUG)
    {
        if ($target->isStdout() || $target->isStderr()) {
            $this->addTarget($target, $levels, $this->StdioTargets);
        } else {
            $this->addTarget($target, $levels, $this->ExceptStdioTargets);
        }
        if ($target->isTty()) {
            $this->addTarget($target, $levels, $this->TtyTargets);
        }
        $this->addTarget($target, $levels, $this->Targets);

        return $this;
    }

    private function addTarget(ConsoleTarget $target, array $levels, array &$targets)
    {
        foreach ($levels as $level) {
            $targets[$level][] = $target;
        }
    }

    private function registerDefaultTargets()
    {
        // Log output to:
        //
        //     sys_get_temp_dir() . '/<script_basename>-<realpath_hash>-<user_id>.log'
        $this->registerTarget(StreamTarget::fromPath(File::getStablePath('.log')), ConsoleLevels::ALL_DEBUG);
        $this->registerDefaultStdioTargets();
    }

    /**
     * Register STDOUT and/or STDERR as targets in their default configuration
     *
     * Returns without taking any action if `$replace` is `false` and a target
     * backed by STDOUT or STDERR has already been registered.
     *
     * @return $this
     */
    public function registerDefaultStdioTargets(bool $replace = false)
    {
        switch (strtolower(Env::get('CONSOLE_OUTPUT', ''))) {
            case 'stderr':
                return $this->registerStdioTarget($replace, STDERR);

            case 'stdout':
                return $this->registerStdioTarget($replace, STDOUT);
        }

        if (stream_isatty(STDERR) && !stream_isatty(STDOUT)) {
            return $this->registerStderrTarget($replace);
        }

        return $this->registerStdioTargets($replace);
    }

    /**
     * Register STDOUT and STDERR as targets if running on the command line
     *
     * Returns without taking any action if `$replace` is `false` and a target
     * backed by STDOUT or STDERR has already been registered.
     *
     * @return $this
     */
    public function registerStdioTargets(bool $replace = false)
    {
        if (PHP_SAPI != 'cli' || ($this->StdioTargets && !$replace)) {
            return $this;
        }

        // Send errors and warnings to STDERR, everything else to STDOUT
        $stderrLevels = ConsoleLevels::ERRORS;
        $stdoutLevels = Env::debug()
            ? ConsoleLevels::INFO_DEBUG
            : ConsoleLevels::INFO;
        $this->clearStdioTargets();
        $this->registerTarget(new StreamTarget(STDERR), $stderrLevels);
        $this->registerTarget(new StreamTarget(STDOUT), $stdoutLevels);

        return $this;
    }

    /**
     * Register STDERR as a target if running on the command line
     *
     * Returns without taking any action if `$replace` is `false` and a target
     * backed by STDOUT or STDERR has already been registered.
     *
     * @return $this
     */
    public function registerStderrTarget(bool $replace = false)
    {
        // Send everything to STDERR
        return $this->registerStdioTarget($replace, STDERR);
    }

    private function registerStdioTarget(bool $replace, $stream)
    {
        if (PHP_SAPI != 'cli' || ($this->StdioTargets && !$replace)) {
            return $this;
        }

        $levels = Env::debug()
            ? ConsoleLevels::ALL_DEBUG
            : ConsoleLevels::ALL;
        $this->clearStdioTargets();
        $this->registerTarget(new StreamTarget($stream), $levels);

        return $this;
    }

    private function clearStdioTargets(): void
    {
        if (!$this->StdioTargets) {
            return;
        }
        $this->removeTargets($this->StdioTargets, $this->Targets);
        $this->removeTargets($this->StdioTargets, $this->TtyTargets);
        $this->StdioTargets = [];
    }

    private function removeTargets(array $remove, array &$from): void
    {
        foreach (array_keys($remove) as $level) {
            if ($from[$level] ?? null) {
                $from[$level] = array_udiff(
                    $from[$level],
                    $remove[$level],
                    fn($a, $b) => $a <=> $b
                );
            }
        }
    }

    /**
     * Call setPrefix on registered targets
     *
     * `$prefix` is applied to all registered targets by default. Set `$stdio`
     * to `false` to exclude targets backed by STDOUT or STDERR, or set
     * `$exceptStdio` to `false` to exclude targets other than STDOUT and
     * STDERR.
     *
     * Default targets are registered if this method is called before an
     * explicit target has been registered.
     *
     * @param bool $ttyOnly If `true`, only call
     * {@see ConsoleTarget::setPrefix()} on TTY targets. `$stdio` and/or
     * `$exceptStdio` must also be `true`.
     * @return $this
     */
    public function setTargetPrefix(
        ?string $prefix,
        bool $ttyOnly = false,
        bool $stdio = true,
        bool $exceptStdio = true
    ) {
        if (!$this->Targets) {
            $this->registerDefaultTargets();
        }

        $targets = $stdio && $exceptStdio
            ? $this->Targets
            : ($stdio
                ? $this->StdioTargets
                : ($exceptStdio
                    ? $this->ExceptStdioTargets
                    : null));

        if (is_null($targets)) {
            throw new RuntimeException('No targets selected');
        }

        $applied = [];
        foreach ($targets as $levelTargets) {
            foreach ($levelTargets as $target) {
                if (!in_array($target, $applied, true)) {
                    $applied[] = $target;
                    if ($ttyOnly && !$target->isTty()) {
                        continue;
                    }
                    $target->setPrefix($prefix);
                }
            }
        }

        return $this;
    }

    public function setFacade(string $name)
    {
        $this->Facade = $name;

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
    public function summary(string $finishedText = 'Command finished', string $successText = 'without errors')
    {
        $msg1 = trim($finishedText);
        if (!($this->Warnings || $this->Errors)) {
            return $this->write(Level::INFO, $msg1, $successText, ' // ');
        }

        $msg2 = 'with ' . Convert::plural($this->Errors, 'error', 'errors', true);
        if ($this->Warnings) {
            $msg2 .= ' and ' . Convert::plural($this->Warnings, 'warning', 'warnings', true);
        }

        return $this->write(
            $this->Errors ? Level::ERROR : Level::WARNING,
            $msg1,
            $msg2,
            $this->Errors ? ' !! ' : '  ! '
        );
    }

    /**
     * Send a message to registered targets
     *
     * @return $this
     */
    private function write(
        int $level,
        string $msg1,
        ?string $msg2,
        string $prefix,
        ?Throwable $ex = null,
        bool $formatByLevel = true
    ) {
        return $this->_write($level, $msg1, $msg2, $prefix, $ex, $this->Targets, $formatByLevel);
    }

    /**
     * Send a message to registered TTY targets
     *
     * @return $this
     */
    private function writeTty(int $level, string $msg1, ?string $msg2, string $prefix, ?Throwable $ex = null)
    {
        return $this->_write($level, $msg1, $msg2, $prefix, $ex, $this->TtyTargets);
    }

    /**
     * Send a message to registered targets once per run
     *
     * @return $this
     */
    private function writeOnce(
        int $level,
        string $msg1,
        ?string $msg2,
        string $prefix,
        ?Throwable $ex = null,
        bool $formatByLevel = true
    ) {
        $hash = Compute::hash($level, $msg1, $msg2, $prefix);
        if (($this->Written[$hash] = ($this->Written[$hash] ?? 0) + 1) < 2) {
            return $this->write($level, $msg1, $msg2, $prefix, $ex, $formatByLevel);
        }

        return $this;
    }

    /**
     * Print "$msg"
     *
     * @return $this
     */
    public function print(string $msg, int $level = Level::INFO, bool $formatByLevel = true)
    {
        return $this->write($level, $msg, null, '', null, $formatByLevel);
    }

    /**
     * Print "$msg" to I/O stream targets (STDOUT or STDERR)
     *
     * @return $this
     */
    public function out(string $msg, int $level = Level::INFO, bool $formatByLevel = true)
    {
        return $this->_write($level, $msg, null, '', null, $this->StdioTargets, $formatByLevel);
    }

    /**
     * Print "$msg" to TTY targets
     *
     * @return $this
     */
    public function tty(string $msg, int $level = Level::INFO, bool $formatByLevel = true)
    {
        return $this->_write($level, $msg, null, '', null, $this->TtyTargets, $formatByLevel);
    }

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     *
     * This method increments the message counter for `$level`.
     *
     * @return $this
     */
    public function message(
        int $level,
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $prefixByLevel = true,
        bool $formatByLevel = true
    ) {
        return $this->count($level)
                    ->write(
                        $level,
                        $msg1,
                        $msg2,
                        $prefixByLevel ? self::LEVEL_PREFIX[$level] : '',
                        $ex,
                        $formatByLevel
                    );
    }

    /**
     * Print "$msg1 $msg2" with prefix and formatting optionally based on $level
     * once per run
     *
     * This method increments the message counter for `$level`.
     *
     * @return $this
     */
    public function messageOnce(
        int $level,
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $prefixByLevel = true,
        bool $formatByLevel = true
    ) {
        return $this->count($level)
                    ->writeOnce(
                        $level,
                        $msg1,
                        $msg2,
                        $prefixByLevel ? self::LEVEL_PREFIX[$level] : '',
                        $ex,
                        $formatByLevel
                    );
    }

    /**
     * Increment the message counter for $level without printing anything
     *
     * @return $this
     */
    public function count(int $level)
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
    public function error(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true)
    {
        !$count || $this->Errors++;

        return $this->write(Level::ERROR, $msg1, $msg2, ' !! ', $ex);
    }

    /**
     * Print " !! $msg1 $msg2" with level ERROR once per run
     *
     * @return $this
     */
    public function errorOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true)
    {
        !$count || $this->Errors++;

        return $this->writeOnce(Level::ERROR, $msg1, $msg2, ' !! ', $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING
     *
     * @return $this
     */
    public function warn(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true)
    {
        !$count || $this->Warnings++;

        return $this->write(Level::WARNING, $msg1, $msg2, '  ! ', $ex);
    }

    /**
     * Print "  ! $msg1 $msg2" with level WARNING once per run
     *
     * @return $this
     */
    public function warnOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true)
    {
        !$count || $this->Warnings++;

        return $this->writeOnce(Level::WARNING, $msg1, $msg2, '  ! ', $ex);
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE
     *
     * @return $this
     */
    public function info(string $msg1, ?string $msg2 = null)
    {
        return $this->write(Level::NOTICE, $msg1, $msg2, '==> ');
    }

    /**
     * Print "==> $msg1 $msg2" with level NOTICE once per run
     *
     * @return $this
     */
    public function infoOnce(string $msg1, ?string $msg2 = null)
    {
        return $this->writeOnce(Level::NOTICE, $msg1, $msg2, '==> ');
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO
     *
     * @return $this
     */
    public function log(string $msg1, ?string $msg2 = null)
    {
        return $this->write(Level::INFO, $msg1, $msg2, ' -> ');
    }

    /**
     * Print " -> $msg1 $msg2" with level INFO once per run
     *
     * @return $this
     */
    public function logOnce(string $msg1, ?string $msg2 = null)
    {
        return $this->writeOnce(Level::INFO, $msg1, $msg2, ' -> ');
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
    public function logProgress(string $msg1, ?string $msg2 = null)
    {
        if (!($this->TtyTargets[Level::INFO] ?? null)) {
            return $this;
        }

        if (is_null($msg2)) {
            $msg1 = rtrim($msg1, "\r") . "\r";
        } else {
            $msg2 = rtrim($msg2, "\r") . "\r";
        }

        return $this->writeTty(Level::INFO, $msg1, $msg2, ' -> ');
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
        if (!($this->TtyTargets[Level::INFO] ?? null)) {
            return $this;
        }

        return $this->writeTty(Level::INFO, "\r", null, '');
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG
     *
     * @param int $depth Passed to {@see \Lkrms\Utility\Debugging::getCaller()}.
     * To print your caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    public function debug(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0)
    {
        if ($this->Facade) {
            $depth++;
        }

        $caller = implode('', Debug::getCaller($depth));
        $msg1 = $msg1 ? ' __' . $msg1 . '__' : '';

        return $this->write(Level::DEBUG, "{{$caller}}{$msg1}", $msg2, '--- ', $ex);
    }

    /**
     * Print "--- {CALLER} $msg1 $msg2" with level DEBUG once per run
     *
     * @param int $depth Passed to {@see \Lkrms\Utility\Debugging::getCaller()}.
     * To print your caller's name instead of your own, set `$depth` to 1.
     * @return $this
     */
    public function debugOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0)
    {
        if ($this->Facade) {
            $depth++;
        }

        $caller = implode('', Debug::getCaller($depth));

        return $this->writeOnce(Level::DEBUG, "{{$caller}} __" . $msg1 . '__', $msg2, '--- ', $ex);
    }

    /**
     * Create a new message group and print "<<< $msg1 $msg2" with level NOTICE
     *
     * The message group will remain open, and subsequent messages will be
     * indented, until {@see ConsoleWriter::groupEnd()} is called.
     *
     * @return $this
     */
    public function group(string $msg1, ?string $msg2 = null)
    {
        $this->GroupLevel++;
        $this->GroupMessageStack[] = Convert::sparseToString(' ', [$msg1, $msg2]);

        return $this->write(Level::NOTICE, $msg1, $msg2, '>>> ');
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
            $msg = ConsoleFormatter::removeTags($msg);
            $this->write(Level::NOTICE, '', $msg ? "{ $msg } complete" : null, '<<< ');
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
     * Prints " !! Uncaught <exception>: <message> in <file>:<line>" with level
     * `$messageLevel` (default: ERROR), followed by the exception's stack trace
     * with level `$stackTraceLevel` (default: DEBUG).
     *
     * @param int|null $stackTraceLevel If `null`, the exception's stack trace
     * is not printed.
     * @return $this
     */
    public function exception(
        Throwable $exception,
        int $messageLevel = Level::ERROR,
        ?int $stackTraceLevel = Level::DEBUG
    ) {
        $ex = $exception;
        $i = 0;
        do {
            $msg2 = ($msg2 ?? '') . (($i++ ? "\nCaused by __" . get_class($ex) . '__: ' : '')
                . sprintf(
                    '`%s` ~~in %s:%d~~',
                    ConsoleFormatter::escape($ex->getMessage()),
                    $ex->getFile(),
                    $ex->getLine()
                ));
            $ex = $ex->getPrevious();
        } while ($ex);

        $this->count($messageLevel)
             ->write(
                 $messageLevel,
                 'Uncaught __' . get_class($exception) . '__:',
                 $msg2,
                 ' !! ',
                 $exception
             );
        if (is_null($stackTraceLevel)) {
            return $this;
        }
        $this->write(
            $stackTraceLevel,
            '__Stack trace:__',
            "\n`" . ConsoleFormatter::escape($exception->getTraceAsString()) . '`',
            '--- '
        );
        if ($exception instanceof \Lkrms\Exception\Exception) {
            foreach ($exception->getDetail() as $section => $text) {
                $this->write(
                    $stackTraceLevel,
                    "__{$section}:__",
                    "\n`" . ConsoleFormatter::escape($text ?: '') . '`',
                    '--- '
                );
            }
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function _write(
        int $level,
        string $msg1,
        ?string $msg2,
        string $prefix,
        ?Throwable $ex,
        array &$targets,
        bool $formatByLevel = true
    ) {
        if (!$this->Targets) {
            $this->registerDefaultTargets();
        }

        $margin = max(0, $this->GroupLevel) * 4;
        $indent = strlen($prefix);
        $indent = max(0, strpos($msg1, "\n") !== false ? $indent : $indent - 4);

        if ($ex) {
            $context = ['exception' => $ex];
        }

        /** @var ConsoleTarget $target */
        foreach ($targets[$level] ?? [] as $target) {
            $target->setMessageFormatting($formatByLevel);
            $formatter = $target->getFormatter();
            $_msg1 = $formatter->format($msg1);
            $_msg2 = is_null($msg2) ? null : $formatter->format($msg2);

            if ($margin + $indent && strpos($msg1, "\n") !== false) {
                $_msg1 = str_replace("\n", "\n" . str_repeat(' ', $margin + $indent), $_msg1);
            }

            if (!is_null($_msg2)) {
                $_msg2 = strpos($msg2, "\n") !== false
                    ? str_replace("\n", "\n" . str_repeat(' ', $margin + $indent + 2), "\n" . ltrim($_msg2))
                    : ($_msg1 ? ' ' : '') . $_msg2;
            }

            $message = $target->getMessageFormat($level)->apply($_msg1, $_msg2, $prefix);
            $target->write($level, str_repeat(' ', $margin) . $message, $context ?? []);
        }

        return $this;
    }
}
