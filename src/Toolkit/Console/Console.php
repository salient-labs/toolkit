<?php declare(strict_types=1);

namespace Salient\Console;

use Psr\Log\LoggerInterface;
use Salient\Console\Format\ConsoleFormatter as Formatter;
use Salient\Console\Target\StreamTarget;
use Salient\Contract\Console\Target\HasPrefix;
use Salient\Contract\Console\Target\StreamTargetInterface;
use Salient\Contract\Console\Target\TargetInterface;
use Salient\Contract\Console\ConsoleInterface;
use Salient\Contract\Core\Exception\Exception;
use Salient\Contract\Core\Exception\MultipleErrorException;
use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\FacadeAwareInstanceTrait;
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
 * @implements FacadeAwareInterface<ConsoleInterface>
 */
final class Console implements ConsoleInterface, FacadeAwareInterface, Unloadable
{
    /** @use FacadeAwareInstanceTrait<ConsoleInterface> */
    use FacadeAwareInstanceTrait;

    private ConsoleState $State;

    public function __construct()
    {
        $this->State = new ConsoleState();
    }

    /**
     * @inheritDoc
     */
    public function logger(): LoggerInterface
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
        $this->removeDeregisteredTargets();
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
    public function registerStdioTargets(?bool $debug = null)
    {
        if (\PHP_SAPI !== 'cli') {
            return $this;
        }

        $stderr = $this->getStderrTarget();
        $stderrLevels = self::LEVELS_ERRORS_AND_WARNINGS;

        $stdout = $this->getStdoutTarget();
        $stdoutLevels = $debug ?? Env::getDebug()
            ? self::LEVELS_INFO
            : self::LEVELS_INFO_EXCEPT_DEBUG;

        return $this
            ->onlyDeregisterStdioTargets()
            ->registerTarget($stderr, $stderrLevels)
            ->registerTarget($stdout, $stdoutLevels)
            ->removeDeregisteredTargets();
    }

    /**
     * @inheritDoc
     */
    public function registerStderrTarget(?bool $debug = null)
    {
        if (\PHP_SAPI !== 'cli') {
            return $this;
        }

        return $this->registerStdioTarget($this->getStderrTarget(), $debug);
    }

    /**
     * @return $this
     */
    private function registerStdioTarget(TargetInterface $target, ?bool $debug = null)
    {
        $levels = $debug ?? Env::getDebug()
            ? self::LEVELS_ALL
            : self::LEVELS_ALL_EXCEPT_DEBUG;

        return $this
            ->onlyDeregisterStdioTargets()
            ->registerTarget($target, $levels)
            ->removeDeregisteredTargets();
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
        TargetInterface $target,
        array $levels = Console::LEVELS_ALL
    ) {
        $type = 0;

        if ($target instanceof StreamTargetInterface) {
            $type |= self::TARGET_STREAM;

            if ($target->isStdout()) {
                $type |= self::TARGET_STDIO | self::TARGET_STDOUT;
                $this->State->StdoutTarget = $target;
            }

            if ($target->isStderr()) {
                $type |= self::TARGET_STDIO | self::TARGET_STDERR;
                $this->State->StderrTarget = $target;
            }

            if ($type & self::TARGET_STDIO) {
                $targetsByLevel[] = &$this->State->StdioTargetsByLevel;
            }

            if ($target->isTty()) {
                $type |= self::TARGET_TTY;
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
    public function deregisterTarget(TargetInterface $target)
    {
        return $this->onlyDeregisterTarget($target)->removeDeregisteredTargets();
    }

    /**
     * @return $this
     */
    private function onlyDeregisterTarget(TargetInterface $target)
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
                if (!$target instanceof StreamTargetInterface) {
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
    private function removeDeregisteredTargets()
    {
        // Reduce `$this->State->DeregisteredTargets` to targets not
        // subsequently re-registered
        $this->State->DeregisteredTargets = array_diff_key(
            $this->State->DeregisteredTargets,
            $this->State->Targets,
        );
        foreach ($this->State->DeregisteredTargets as $i => $target) {
            unset($this->State->TargetTypeFlags[$i]);
            unset($this->State->DeregisteredTargets[$i]);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getTargets(?int $level = null, int $targetFlags = 0): array
    {
        $targets = $level === null
            ? $this->State->Targets
            : $this->State->TargetsByLevel[$level] ?? [];
        if ($targetFlags) {
            $targets = $this->filterTargets($targets, $targetFlags);
        }
        return array_values($targets);
    }

    /**
     * @inheritDoc
     */
    public function setPrefix(?string $prefix, int $targetFlags = 0)
    {
        foreach ($this->filterTargets($this->State->Targets, $targetFlags) as $target) {
            if ($target instanceof HasPrefix) {
                $target->setPrefix($prefix);
            }
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStdoutTarget(): StreamTargetInterface
    {
        return $this->State->StdoutTarget ??= StreamTarget::fromStream(\STDOUT);
    }

    /**
     * @inheritDoc
     */
    public function getStderrTarget(): StreamTargetInterface
    {
        return $this->State->StderrTarget ??= StreamTarget::fromStream(\STDERR);
    }

    /**
     * @inheritDoc
     */
    public function getTtyTarget(): StreamTargetInterface
    {
        return $this->State->StderrTarget && $this->State->StderrTarget->isTty()
            ? $this->State->StderrTarget
            : ($this->State->StdoutTarget && $this->State->StdoutTarget->isTty()
                ? $this->State->StdoutTarget
                : (($stderr = $this->getStderrTarget())->isTty()
                    ? $stderr
                    : (($stdout = $this->getStdoutTarget())->isTty()
                        ? $stdout
                        : $stderr)));
    }

    /**
     * @inheritDoc
     */
    public function errors(): int
    {
        return $this->State->Errors;
    }

    /**
     * @inheritDoc
     */
    public function warnings(): int
    {
        return $this->State->Warnings;
    }

    /**
     * @inheritDoc
     */
    public function escape(string $string, bool $escapeNewlines = false): string
    {
        return Formatter::escapeTags($string, $escapeNewlines);
    }

    /**
     * @inheritDoc
     */
    public function summary(
        string $finishedText = 'Command finished',
        string $successText = 'without errors',
        bool $withResourceUsage = false,
        bool $withoutErrorsAndWarnings = false,
        bool $withGenericType = false
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
        $errors = $this->State->Errors;
        $warnings = $this->State->Warnings;
        if (
            (!$errors && !$warnings)
            // If output is identical for success and failure, print a
            // success message
            || ($withoutErrorsAndWarnings && $successText === '')
        ) {
            return $this->write(
                self::LEVEL_INFO,
                Arr::implode(' ', [$msg1, $successText, $usage ?? null], ''),
                null,
                $withGenericType
                    ? self::TYPE_SUMMARY
                    : self::TYPE_SUCCESS,
            );
        }

        if (!$withoutErrorsAndWarnings) {
            $msg2 = 'with ' . Inflect::format($errors, '{{#}} {{#:error}}');
            if ($warnings) {
                $msg2 .= ' and ' . Inflect::format($warnings, '{{#}} {{#:warning}}');
            }
        }

        return $this->write(
            $withoutErrorsAndWarnings || $withGenericType
                ? self::LEVEL_INFO
                : ($errors ? self::LEVEL_ERROR : self::LEVEL_WARNING),
            Arr::implode(' ', [$msg1, $msg2 ?? null, $usage ?? null], ''),
            null,
            $withoutErrorsAndWarnings || $withGenericType
                ? self::TYPE_SUMMARY
                : self::TYPE_FAILURE,
        );
    }

    /**
     * @inheritDoc
     */
    public function print(
        string $msg,
        int $level = Console::LEVEL_INFO
    ) {
        return $this->_write($level, $msg, null, self::TYPE_UNFORMATTED, null, $this->State->TargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function printStdio(
        string $msg,
        int $level = Console::LEVEL_INFO
    ) {
        return $this->_write($level, $msg, null, self::TYPE_UNFORMATTED, null, $this->State->StdioTargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function printTty(
        string $msg,
        int $level = Console::LEVEL_INFO
    ) {
        return $this->_write($level, $msg, null, self::TYPE_UNFORMATTED, null, $this->State->TtyTargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function printStdout(
        string $msg,
        int $level = Console::LEVEL_INFO
    ) {
        $targets = [$level => [$this->getStdoutTarget()]];
        return $this->_write($level, $msg, null, self::TYPE_UNFORMATTED, null, $targets);
    }

    /**
     * @inheritDoc
     */
    public function message(
        string $msg1,
        ?string $msg2 = null,
        int $level = Console::LEVEL_INFO,
        int $type = Console::TYPE_UNDECORATED,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        if ($count) {
            $this->count($level);
        }
        return $this->write($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * @inheritDoc
     */
    public function messageOnce(
        string $msg1,
        ?string $msg2 = null,
        int $level = Console::LEVEL_INFO,
        int $type = Console::TYPE_UNDECORATED,
        ?Throwable $ex = null,
        bool $count = true
    ) {
        if ($count) {
            $this->count($level);
        }
        return $this->writeOnce($level, $msg1, $msg2, $type, $ex);
    }

    /**
     * @inheritDoc
     */
    public function count(int $level)
    {
        switch ($level) {
            case self::LEVEL_EMERGENCY:
            case self::LEVEL_ALERT:
            case self::LEVEL_CRITICAL:
            case self::LEVEL_ERROR:
                $this->State->Errors++;
                break;

            case self::LEVEL_WARNING:
                $this->State->Warnings++;
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
        !$count || $this->State->Errors++;

        return $this->write(self::LEVEL_ERROR, $msg1, $msg2, self::TYPE_STANDARD, $ex);
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
        !$count || $this->State->Errors++;

        return $this->writeOnce(self::LEVEL_ERROR, $msg1, $msg2, self::TYPE_STANDARD, $ex);
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
        !$count || $this->State->Warnings++;

        return $this->write(self::LEVEL_WARNING, $msg1, $msg2, self::TYPE_STANDARD, $ex);
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
        !$count || $this->State->Warnings++;

        return $this->writeOnce(self::LEVEL_WARNING, $msg1, $msg2, self::TYPE_STANDARD, $ex);
    }

    /**
     * @inheritDoc
     */
    public function info(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->write(self::LEVEL_NOTICE, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function infoOnce(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->writeOnce(self::LEVEL_NOTICE, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function log(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->write(self::LEVEL_INFO, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function logOnce(
        string $msg1,
        ?string $msg2 = null
    ) {
        return $this->writeOnce(self::LEVEL_INFO, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function logProgress(
        string $msg1,
        ?string $msg2 = null
    ) {
        if (!($this->State->TtyTargetsByLevel[self::LEVEL_INFO] ?? null)) {
            return $this;
        }

        if ($msg2 === null || $msg2 === '') {
            $msg1 = rtrim($msg1, "\r") . "\r";
        } else {
            $msg2 = rtrim($msg2, "\r") . "\r";
        }

        return $this->writeTty(self::LEVEL_INFO, $msg1, $msg2, self::TYPE_PROGRESS);
    }

    /**
     * @inheritDoc
     */
    public function clearProgress()
    {
        if (!($this->State->TtyTargetsByLevel[self::LEVEL_INFO] ?? null)) {
            return $this;
        }

        return $this->writeTty(self::LEVEL_INFO, "\r", null, self::TYPE_UNFORMATTED);
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

        return $this->write(self::LEVEL_DEBUG, "{{$caller}}{$msg1}", $msg2, self::TYPE_STANDARD, $ex);
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

        return $this->writeOnce(self::LEVEL_DEBUG, "{{$caller}} __" . $msg1 . '__', $msg2, self::TYPE_STANDARD, $ex);
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
        return $this->write(self::LEVEL_NOTICE, $msg1, $msg2, self::TYPE_GROUP_START);
    }

    /**
     * @inheritDoc
     */
    public function groupEnd()
    {
        [$msg1, $msg2] = array_pop($this->State->GroupMessageStack) ?? [null, null];
        if ($msg1 !== null) {
            $this->write(self::LEVEL_NOTICE, $msg1, $msg2, self::TYPE_GROUP_END);
        }
        if ($this->State->LastWritten !== [__METHOD__, '']) {
            $this->printStdio('', self::LEVEL_NOTICE);
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
        int $level = Console::LEVEL_ERROR,
        ?int $traceLevel = Console::LEVEL_DEBUG,
        bool $count = true
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

            if ($level <= self::LEVEL_ERROR || ($debug ??= Env::getDebug())) {
                $file = $this->escape($ex->getFile());
                $line = $ex->getLine();
                $msg2 .= sprintf('%s ~~in %s:%d~~', $message, $file, $line);
            } else {
                $msg2 .= $message;
            }
        } while ($ex = $ex->getPrevious());

        $class = $this->escape(Get::basename(get_class($exception)));
        if ($count) {
            $this->count($level);
        }
        $this->write(
            $level,
            "{$class}:",
            $msg2,
            self::TYPE_STANDARD,
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
     * @param array<self::LEVEL_*,TargetInterface[]> $targets
     * @return TargetInterface[]
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
     * @param self::LEVEL_* $level
     * @param self::TYPE_* $type
     * @return $this
     */
    private function write(
        int $level,
        string $msg1,
        ?string $msg2,
        int $type = self::TYPE_STANDARD,
        ?Throwable $ex = null,
        bool $msg2HasTags = false
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TargetsByLevel, $msg2HasTags);
    }

    /**
     * Send a message to registered targets once per run
     *
     * @param self::LEVEL_* $level
     * @param self::TYPE_* $type
     * @return $this
     */
    private function writeOnce(
        int $level,
        string $msg1,
        ?string $msg2,
        int $type = self::TYPE_STANDARD,
        ?Throwable $ex = null
    ) {
        $hash = Get::hash(implode("\0", [$level, $msg1, $msg2, $type]));
        if (isset($this->State->Written[$hash])) {
            return $this;
        }
        $this->State->Written[$hash] = true;
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TargetsByLevel);
    }

    /**
     * Send a message to registered TTY targets
     *
     * @param self::LEVEL_* $level
     * @param self::TYPE_* $type
     * @return $this
     */
    private function writeTty(
        int $level,
        string $msg1,
        ?string $msg2,
        int $type = self::TYPE_STANDARD,
        ?Throwable $ex = null
    ) {
        return $this->_write($level, $msg1, $msg2, $type, $ex, $this->State->TtyTargetsByLevel);
    }

    /**
     * @template T of TargetInterface
     *
     * @param self::LEVEL_* $level
     * @param self::TYPE_* $type
     * @param array<self::LEVEL_*,T[]> $targets
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
            $this->registerTarget($logTarget, self::LEVELS_ALL);
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

            if ($type === self::TYPE_PROGRESS) {
                $formatter = $formatter->withSpinnerState($this->State->SpinnerState);
            }

            $message = $formatter->formatMessage($_msg1, $_msg2, $level, $type);
            $target->write($level, str_repeat(' ', $margin) . $message, $context ?? []);
        }

        $this->State->LastWritten = [];

        return $this;
    }

    /**
     * @param array<int,TargetInterface> $targets
     * @param int-mask-of<self::TARGET_*> $targetFlags
     * @return array<int,TargetInterface>
     */
    private function filterTargets(array $targets, int $targetFlags): array
    {
        $invert = false;
        if ($targetFlags & self::TARGET_INVERT) {
            $targetFlags &= ~self::TARGET_INVERT;
            $invert = true;
        }
        foreach ($targets as $targetId => $target) {
            if (($targetFlags === 0 && !$invert) || (
                $this->State->TargetTypeFlags[$targetId] & $targetFlags
                xor $invert
            )) {
                $filtered[$targetId] = $target;
            }
        }
        return $filtered ?? [];
    }
}

/**
 * @internal
 */
final class ConsoleState
{
    /** @var array<Console::LEVEL_*,StreamTargetInterface[]> */
    public array $StdioTargetsByLevel = [];
    /** @var array<Console::LEVEL_*,StreamTargetInterface[]> */
    public array $TtyTargetsByLevel = [];
    /** @var array<Console::LEVEL_*,TargetInterface[]> */
    public array $TargetsByLevel = [];
    /** @var array<int,TargetInterface> */
    public array $Targets = [];
    /** @var array<int,TargetInterface> */
    public array $DeregisteredTargets = [];
    /** @var array<int,int-mask-of<Console::TARGET_*>> */
    public array $TargetTypeFlags = [];
    public ?StreamTargetInterface $StdoutTarget = null;
    public ?StreamTargetInterface $StderrTarget = null;
    public int $GroupLevel = -1;
    /** @var array<array{string|null,string|null}> */
    public array $GroupMessageStack = [];
    public int $Errors = 0;
    public int $Warnings = 0;
    /** @var array<string,true> */
    public array $Written = [];
    /** @var string[] */
    public array $LastWritten = [];
    /** @var array{int<0,max>,float}|null */
    public ?array $SpinnerState;
    public LoggerInterface $Logger;

    private function __clone() {}
}
