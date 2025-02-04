<?php declare(strict_types=1);

namespace Salient\Console;

use Psr\Log\LoggerInterface;
use Salient\Console\Support\ConsoleWriterState;
use Salient\Console\Target\StreamTarget;
use Salient\Console\ConsoleFormatter as Formatter;
use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Contract\Catalog\MessageLevelGroup as LevelGroup;
use Salient\Contract\Console\ConsoleFormatterInterface as FormatterInterface;
use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Console\ConsoleTargetInterface;
use Salient\Contract\Console\ConsoleTargetInterface as Target;
use Salient\Contract\Console\ConsoleTargetPrefixInterface as TargetPrefix;
use Salient\Contract\Console\ConsoleTargetStreamInterface as TargetStream;
use Salient\Contract\Console\ConsoleTargetTypeFlag as TargetTypeFlag;
use Salient\Contract\Console\ConsoleWriterInterface;
use Salient\Contract\Core\Exception\Exception;
use Salient\Contract\Core\Exception\MultipleErrorException;
use Salient\Contract\Core\FacadeAwareInterface;
use Salient\Contract\Core\FacadeInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\HasFacade;
use Salient\Core\Facade\Console;
use Salient\Utility\Exception\InvalidEnvironmentException;
use Salient\Utility\Arr;
use Salient\Utility\Debug;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Format;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Str;
use Salient\Utility\Sys;
use Throwable;

/**
 * Logs messages to registered targets
 *
 * {@see ConsoleWriter} methods should generally be called via the
 * {@see Console} facade. If a {@see ConsoleWriter} instance is required, call
 * {@see Console::getInstance()}.
 *
 * @implements FacadeAwareInterface<FacadeInterface<ConsoleWriterInterface>>
 */
final class ConsoleWriter implements ConsoleWriterInterface, FacadeAwareInterface, Unloadable
{
    /** @use HasFacade<FacadeInterface<ConsoleWriterInterface>> */
    use HasFacade;

    private ConsoleWriterState $State;

    public function __construct()
    {
        $this->State = new ConsoleWriterState();
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->State->Logger ??= new ConsoleLogger($this);
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        foreach ($this->State->Targets as $target) {
            $this->onlyDeregisterTarget($target);
        }
        $this->closeDeregisteredTargets();
    }

    /**
     * @return $this
     */
    private function maybeRegisterStdioTargets()
    {
        $output = Env::get('console_target', null);

        if ($output !== null) {
            switch (Str::lower($output)) {
                case 'stderr':
                    $target = $this->getStderrTarget();
                    // No break
                case 'stdout':
                    $target ??= $this->getStdoutTarget();
                    return $this->registerStdioTarget($target);

                default:
                    throw new InvalidEnvironmentException(
                        sprintf('Invalid console_target value: %s', $output)
                    );
            }
        }

        if (stream_isatty(\STDERR) && !stream_isatty(\STDOUT)) {
            return $this->registerStderrTarget();
        }

        return $this->registerStdioTargets();
    }

    /**
     * @inheritDoc
     */
    public function registerStdioTargets()
    {
        if (\PHP_SAPI !== 'cli') {
            return $this;
        }

        $stderr = $this->getStderrTarget();
        $stderrLevels = LevelGroup::ERRORS_AND_WARNINGS;

        $stdout = $this->getStdoutTarget();
        $stdoutLevels = Env::getDebug()
            ? LevelGroup::INFO
            : LevelGroup::INFO_EXCEPT_DEBUG;

        return $this
            ->onlyDeregisterStdioTargets()
            ->registerTarget($stderr, $stderrLevels)
            ->registerTarget($stdout, $stdoutLevels)
            ->closeDeregisteredTargets();
    }

    /**
     * @inheritDoc
     */
    public function registerStderrTarget()
    {
        if (\PHP_SAPI !== 'cli') {
            return $this;
        }

        return $this->registerStdioTarget($this->getStderrTarget());
    }

    /**
     * @return $this
     */
    private function registerStdioTarget(Target $target)
    {
        $levels = Env::getDebug()
            ? LevelGroup::ALL
            : LevelGroup::ALL_EXCEPT_DEBUG;

        return $this
            ->onlyDeregisterStdioTargets()
            ->registerTarget($target, $levels)
            ->closeDeregisteredTargets();
    }

    /**
     * @return $this
     */
    private function onlyDeregisterStdioTargets()
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
     * @inheritDoc
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
     * @inheritDoc
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
            $this->State->DeregisteredTargets,
            $this->State->Targets,
        );
        foreach ($this->State->DeregisteredTargets as $i => $target) {
            $target->close();
            unset($this->State->TargetTypeFlags[$i]);
            unset($this->State->DeregisteredTargets[$i]);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTargets(?int $level = null, int $flags = 0): array
    {
        $targets = $level === null
            ? $this->State->Targets
            : $this->State->TargetsByLevel[$level] ?? [];
        if ($flags) {
            $targets = $this->filterTargets($targets, $flags);
        }
        return array_values($targets);
    }

    /**
     * @inheritDoc
     */
    public function setTargetPrefix(?string $prefix, int $flags = 0)
    {
        foreach ($this->filterTargets($this->State->Targets, $flags) as $target) {
            if ($target instanceof TargetPrefix) {
                $target->setPrefix($prefix);
            }
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getWidth(int $level = Level::INFO): ?int
    {
        return $this->maybeGetTtyTarget($level)->getWidth();
    }

    /**
     * @inheritDoc
     */
    public function getFormatter(int $level = Level::INFO): FormatterInterface
    {
        return $this->maybeGetTtyTarget($level)->getFormatter();
    }

    /**
     * @param Level::* $level
     */
    private function maybeGetTtyTarget(int $level): TargetStream
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
     * @inheritDoc
     */
    public function getStdoutTarget(): TargetStream
    {
        return $this->State->StdoutTarget ??= StreamTarget::fromStream(\STDOUT);
    }

    /**
     * @inheritDoc
     */
    public function getStderrTarget(): TargetStream
    {
        return $this->State->StderrTarget ??= StreamTarget::fromStream(\STDERR);
    }

    /**
     * @inheritDoc
     */
    public function getErrorCount(): int
    {
        return $this->State->ErrorCount;
    }

    /**
     * @inheritDoc
     */
    public function getWarningCount(): int
    {
        return $this->State->WarningCount;
    }

    /**
     * @inheritDoc
     */
    public function escape(string $string): string
    {
        return Formatter::escapeTags($string);
    }

    /**
     * @inheritDoc
     */
    public function summary(
        string $finishedText = 'Command finished',
        string $successText = 'without errors',
        bool $withResourceUsage = false,
        bool $withoutErrorCount = false,
        bool $withStandardMessageType = false
    ) {
        if ($withResourceUsage) {
            /** @var float */
            $requestTime = $_SERVER['REQUEST_TIME_FLOAT'];
            $usage = sprintf(
                'in %.3fs (%s memory used)',
                microtime(true) - $requestTime,
                Format::bytes(memory_get_peak_usage()),
            );
        }

        $msg1 = rtrim($finishedText);
        $errors = $this->State->ErrorCount;
        $warnings = $this->State->WarningCount;
        if (
            (!$errors && !$warnings)
            // If output is identical for success and failure, print a
            // success message
            || ($withoutErrorCount && $successText === '')
        ) {
            return $this->write(
                Level::INFO,
                Arr::implode(' ', [$msg1, $successText, $usage ?? null], ''),
                null,
                $withStandardMessageType
                    ? MessageType::SUMMARY
                    : MessageType::SUCCESS,
            );
        }

        if (!$withoutErrorCount) {
            $msg2 = 'with ' . Inflect::format($errors, '{{#}} {{#:error}}');
            if ($warnings) {
                $msg2 .= ' and ' . Inflect::format($warnings, '{{#}} {{#:warning}}');
            }
        }

        return $this->write(
            $withoutErrorCount || $withStandardMessageType
                ? Level::INFO
                : ($errors ? Level::ERROR : Level::WARNING),
            Arr::implode(' ', [$msg1, $msg2 ?? null, $usage ?? null], ''),
            null,
            $withoutErrorCount || $withStandardMessageType
                ? MessageType::SUMMARY
                : MessageType::FAILURE,
        );
    }

    /**
     * @inheritDoc
     */
    public function print(
        string $msg,
        int $level = Level::INFO,
        int $type = MessageType::UNFORMATTED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->State->TargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function printOut(
        string $msg,
        int $level = Level::INFO,
        int $type = MessageType::UNFORMATTED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->State->StdioTargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function printTty(
        string $msg,
        int $level = Level::INFO,
        int $type = MessageType::UNFORMATTED
    ) {
        return $this->_write($level, $msg, null, $type, null, $this->State->TtyTargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function printStdout(
        string $msg,
        int $level = Level::INFO,
        int $type = MessageType::UNFORMATTED
    ) {
        $targets = [$level => [$this->getStdoutTarget()]];
        return $this->_write($level, $msg, null, $type, null, $targets);
    }

    /**
     * @inheritDoc
     */
    public function printStderr(
        string $msg,
        int $level = Level::INFO,
        int $type = MessageType::UNFORMATTED
    ) {
        $targets = [$level => [$this->getStderrTarget()]];
        return $this->_write($level, $msg, null, $type, null, $targets);
    }

    /**
     * @inheritDoc
     */
    public function message(
        string $msg1,
        ?string $msg2 = null,
        int $level = Level::INFO,
        int $type = MessageType::UNDECORATED,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        if ($count) {
            $this->count($level);
        }
        /** @var int&Level::* $level */
        return $this->write($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * @inheritDoc
     */
    public function messageOnce(
        string $msg1,
        ?string $msg2 = null,
        int $level = Level::INFO,
        int $type = MessageType::UNDECORATED,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        if ($count) {
            $this->count($level);
        }
        /** @var int&Level::* $level */
        return $this->writeOnce($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * @inheritDoc
     */
    public function count($level)
    {
        switch ($level) {
            case Level::EMERGENCY:
            case Level::ALERT:
            case Level::CRITICAL:
            case Level::ERROR:
                $this->State->ErrorCount++;
                break;

            case Level::WARNING:
                $this->State->WarningCount++;
                break;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function error(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->State->ErrorCount++;

        return $this->write(Level::ERROR, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * @inheritDoc
     */
    public function errorOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->State->ErrorCount++;

        return $this->writeOnce(Level::ERROR, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * @inheritDoc
     */
    public function warn(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->State->WarningCount++;

        return $this->write(Level::WARNING, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * @inheritDoc
     */
    public function warnOnce(
        string $msg1,
        ?string $msg2 = null,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        !$count || $this->State->WarningCount++;

        return $this->writeOnce(Level::WARNING, $msg1, $msg2, MessageType::STANDARD, $ex);
    }

    /**
     * @inheritDoc
     */
    public function info(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->write(Level::NOTICE, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function infoOnce(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->writeOnce(Level::NOTICE, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function log(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->write(Level::INFO, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function logOnce(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->writeOnce(Level::INFO, $msg1, $msg2);
    }

    /**
     * @inheritDoc
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

        return $this->writeTty(Level::INFO, $msg1, $msg2, MessageType::PROGRESS);
    }

    /**
     * @inheritDoc
     */
    public function clearProgress()
    {
        if (!($this->State->TtyTargetsByLevel[Level::INFO] ?? null)) {
            return $this;
        }

        return $this->writeTty(Level::INFO, "\r", null, MessageType::UNFORMATTED);
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function group(
        string $msg1,
        ?string $msg2 = null,
        ?string $endMsg1 = null,
        ?string $endMsg2 = null
    ) {
        $this->State->GroupLevel++;
        $this->State->GroupMessageStack[] = [$endMsg1, $endMsg1 === null ? null : $endMsg2];
        return $this->write(Level::NOTICE, $msg1, $msg2, MessageType::GROUP_START);
    }

    /**
     * @inheritDoc
     */
    public function groupEnd()
    {
        [$msg1, $msg2] = array_pop($this->State->GroupMessageStack) ?? [null, null];
        if ($msg1 !== null) {
            $this->write(Level::NOTICE, $msg1, $msg2, MessageType::GROUP_END);
        }
        if ($this->State->LastWritten !== [__METHOD__, '']) {
            $this->printOut('', Level::NOTICE);
            $this->State->LastWritten = [__METHOD__, ''];
        }
        if ($this->State->GroupLevel > -1) {
            $this->State->GroupLevel--;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function exception(
        Throwable $exception,
        int $level = Level::ERROR,
        ?int $traceLevel = Level::DEBUG
    ) {
        $ex = $exception;
        $msg2 = '';
        $i = 0;
        do {
            if ($i++) {
                $class = $this->escape(Get::basename(get_class($ex)));
                $msg2 .= sprintf("\nCaused by __%s__: ", $class);
            }

            if (
                $ex instanceof MultipleErrorException
                && !$ex->hasUnreportedErrors()
            ) {
                $message = $this->escape($ex->getMessageOnly());
            } else {
                $message = $this->escape($ex->getMessage());
            }

            if ($level <= Level::ERROR || ($debug ??= Env::getDebug())) {
                $file = $this->escape($ex->getFile());
                $line = $ex->getLine();
                $msg2 .= sprintf('%s ~~in %s:%d~~', $message, $file, $line);
            } else {
                $msg2 .= $message;
            }
        } while ($ex = $ex->getPrevious());

        $class = $this->escape(Get::basename(get_class($exception)));
        $this->count($level)->write(
            $level,
            "{$class}:",
            $msg2,
            MessageType::STANDARD,
            $exception,
            true
        );
        if ($traceLevel === null) {
            return $this;
        }
        $this->write(
            $traceLevel,
            'Stack trace:',
            "\n" . $exception->getTraceAsString()
        );
        if ($exception instanceof Exception) {
            foreach ($exception->getMetadata() as $key => $value) {
                $value = rtrim((string) $value, "\n");
                $this->write($traceLevel, "{$key}:", "\n{$value}");
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
     * @param int&Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function write(
        int $level,
        string $msg1,
        ?string $msg2,
        int $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TargetsByLevel, $msg2HasTags);
    }

    /**
     * Send a message to registered targets once per run
     *
     * @param int&Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function writeOnce(
        int $level,
        string $msg1,
        ?string $msg2,
        int $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        $hash = Get::hash(implode("\0", [$level, $msg1, $msg2, $type, $msg2HasTags]));
        if (isset($this->State->Written[$hash])) {
            return $this;
        }
        $this->State->Written[$hash] = true;
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TargetsByLevel, $msg2HasTags);
    }

    /**
     * Send a message to registered TTY targets
     *
     * @param int&Level::* $level
     * @param MessageType::* $type
     * @return $this
     */
    private function writeTty(
        int $level,
        string $msg1,
        ?string $msg2,
        int $type = MessageType::STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TtyTargetsByLevel, $msg2HasTags);
    }

    /**
     * @template T of Target
     *
     * @param Level::* $level
     * @param MessageType::* $type
     * @param array<Level::*,T[]> $targets
     * @return $this
     */
    private function _write(
        int $level,
        string $msg1,
        ?string $msg2,
        int $type,
        ?Throwable $ex,
        array &$targets,
        bool $msg2HasTags = false
    ) {
        if (!$this->State->Targets) {
            $logTarget = StreamTarget::fromPath(sprintf(
                '%s/%s-%s-%s.log',
                Sys::getTempDir(),
                Sys::getProgramBasename(),
                Get::hash(File::realpath(Sys::getProgramName())),
                Sys::getUserId(),
            ));
            $this->registerTarget($logTarget, LevelGroup::ALL);
            $this->maybeRegisterStdioTargets();
        }

        // As per PSR-3 Section 1.3
        if ($ex) {
            $context['exception'] = $ex;
        }

        $margin = max(0, $this->State->GroupLevel * 2);

        foreach ($targets[$level] ?? [] as $target) {
            $formatter = $target->getFormatter();

            $indent = mb_strlen($formatter->getMessagePrefix($level, $type));
            $indent = max(0, strpos($msg1, "\n") !== false ? $indent : $indent - 4);

            $_msg1 = $msg1 === '' ? '' : $formatter->format($msg1);

            if ($margin + $indent > 0 && strpos($msg1, "\n") !== false) {
                $_msg1 = str_replace("\n", "\n" . str_repeat(' ', $margin + $indent), $_msg1);
            }

            $_msg2 = null;
            if ($msg2 !== null && $msg2 !== '') {
                $_msg2 = $msg2HasTags ? $formatter->format($msg2) : $msg2;
                $_msg2 = strpos($msg2, "\n") !== false
                    ? str_replace("\n", "\n" . str_repeat(' ', $margin + $indent + 2), "\n" . ltrim($_msg2))
                    : ($_msg1 !== '' ? ' ' : '') . $_msg2;
            }

            if ($type === MessageType::PROGRESS) {
                $formatter = $formatter->withSpinnerState($this->State->SpinnerState);
            }

            $message = $formatter->formatMessage($_msg1, $_msg2, $level, $type);
            $target->write($level, str_repeat(' ', $margin) . $message, $context ?? []);
        }

        $this->State->LastWritten = [];

        return $this;
    }

    /**
     * @param array<int,ConsoleTargetInterface> $targets
     * @param int-mask-of<TargetTypeFlag::*> $flags
     * @return array<int,ConsoleTargetInterface>
     */
    private function filterTargets(array $targets, int $flags): array
    {
        $invert = false;
        if ($flags & TargetTypeFlag::INVERT) {
            $flags &= ~TargetTypeFlag::INVERT;
            $invert = true;
        }
        foreach ($targets as $targetId => $target) {
            if (($flags === 0 && !$invert) || (
                $this->State->TargetTypeFlags[$targetId] & $flags
                xor $invert
            )) {
                $filtered[$targetId] = $target;
            }
        }
        return $filtered ?? [];
    }
}
