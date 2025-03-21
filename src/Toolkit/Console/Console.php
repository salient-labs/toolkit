<?php declare(strict_types=1);

namespace Salient\Console;

use Psr\Log\LoggerInterface;
use Salient\Console\Format\Formatter;
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
use Salient\Utility\Arr;
use Salient\Utility\Debug;
use Salient\Utility\Env;
use Salient\Utility\File;
use Salient\Utility\Format;
use Salient\Utility\Get;
use Salient\Utility\Inflect;
use Salient\Utility\Sys;
use Throwable;

/**
 * @implements FacadeAwareInterface<ConsoleInterface>
 */
class Console implements ConsoleInterface, FacadeAwareInterface, Unloadable
{
    /** @use FacadeAwareInstanceTrait<ConsoleInterface> */
    use FacadeAwareInstanceTrait;

    private ConsoleState $State;

    /**
     * @api
     */
    public function __construct()
    {
        $this->State = new ConsoleState();
    }

    /**
     * @inheritDoc
     */
    public function unload(): void
    {
        foreach ($this->State->Targets as $target) {
            $this->deregisterTarget($target);
        }
    }

    /**
     * @inheritDoc
     */
    public function logger(): LoggerInterface
    {
        return $this->State->Logger ??=
            new ConsoleLogger($this->getReturnable());
    }

    /**
     * @inheritDoc
     */
    public function registerTarget(
        TargetInterface $target,
        array $levels = Console::LEVELS_ALL
    ) {
        $id = spl_object_id($target);
        $register = function (array &$byLevel) use ($target, $levels, $id) {
            foreach ($levels as $level) {
                $byLevel[$level][$id] = $target;
            }
        };
        $flags = 0;
        if ($target instanceof StreamTargetInterface) {
            if ($target->isStdout()) {
                $flags |= self::TARGET_STDIO | self::TARGET_STDOUT;
                $this->State->StdoutTarget = $target;
            }
            if ($target->isStderr()) {
                $flags |= self::TARGET_STDIO | self::TARGET_STDERR;
                $this->State->StderrTarget = $target;
            }
            if ($flags) {
                $register($this->State->StdioTargetsByLevel);
            }
            if ($target->isTty()) {
                $flags |= self::TARGET_TTY;
                $register($this->State->TtyTargetsByLevel);
            }
            $flags |= self::TARGET_STREAM;
        }
        $register($this->State->TargetsByLevel);
        $this->State->Targets[$id] = $target;
        $this->State->TargetFlags[$id] = $flags;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function deregisterTarget(TargetInterface $target)
    {
        $id = spl_object_id($target);
        $deregister = function (array &$byLevel) use ($id) {
            foreach (array_keys($byLevel) as $level) {
                unset($byLevel[$level][$id]);
                if (!$byLevel[$level]) {
                    unset($byLevel[$level]);
                }
            }
        };
        unset($this->State->Targets[$id]);
        unset($this->State->TargetFlags[$id]);
        $deregister($this->State->TargetsByLevel);
        $deregister($this->State->TtyTargetsByLevel);
        $deregister($this->State->StdioTargetsByLevel);
        if ($target instanceof StreamTargetInterface) {
            if ($target === $this->State->StderrTarget) {
                $this->State->StderrTarget = Arr::last($this->filterTargets(self::TARGET_STDERR));
            }
            if ($target === $this->State->StdoutTarget) {
                $this->State->StdoutTarget = Arr::last($this->filterTargets(self::TARGET_STDOUT));
            }
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function registerStderrTarget(?bool $debug = null)
    {
        if (\PHP_SAPI !== 'cli') {
            return $this;
        }
        $debug ??= Env::getDebug();
        $stderr = $this->getStderrTarget();
        return $this
            ->deregisterStdioTargets()
            ->registerTarget($stderr, $debug ? self::LEVELS_ALL : self::LEVELS_ALL_EXCEPT_DEBUG);
    }

    /**
     * @inheritDoc
     */
    public function registerStdioTargets(?bool $debug = null)
    {
        if (\PHP_SAPI !== 'cli') {
            return $this;
        }
        $debug ??= Env::getDebug();
        $stderr = $this->getStderrTarget();
        $stdout = $this->getStdoutTarget();
        return $this
            ->deregisterStdioTargets()
            ->registerTarget($stderr, self::LEVELS_ERRORS_AND_WARNINGS)
            ->registerTarget($stdout, $debug ? self::LEVELS_INFO : self::LEVELS_INFO_EXCEPT_DEBUG);
    }

    /**
     * @inheritDoc
     */
    public function setPrefix(?string $prefix, int $targetFlags = 0)
    {
        foreach ($this->filterTargets($targetFlags) as $target) {
            if ($target instanceof HasPrefix) {
                $target->setPrefix($prefix);
            }
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
        return array_values($targetFlags
            ? $this->filterTargets($targetFlags, $targets)
            : $targets);
    }

    /**
     * @inheritDoc
     */
    public function getStdoutTarget(): StreamTargetInterface
    {
        return $this->State->StdoutTarget ??=
            new StreamTarget(\STDOUT);
    }

    /**
     * @inheritDoc
     */
    public function getStderrTarget(): StreamTargetInterface
    {
        return $this->State->StderrTarget ??=
            new StreamTarget(\STDERR);
    }

    /**
     * @inheritDoc
     */
    public function getTtyTarget(): StreamTargetInterface
    {
        return ($stderr = $this->getStderrTarget())->isTty()
            ? $stderr
            : (($stdout = $this->getStdoutTarget())->isTty()
                ? $stdout
                : $stderr);
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
    public function error(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true)
    {
        return $this->write(self::LEVEL_ERROR, $msg1, $msg2, false, $ex, $count);
    }

    /**
     * @inheritDoc
     */
    public function errorOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true)
    {
        return $this->write(self::LEVEL_ERROR, $msg1, $msg2, true, $ex, $count);
    }

    /**
     * @inheritDoc
     */
    public function warn(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true)
    {
        return $this->write(self::LEVEL_WARNING, $msg1, $msg2, false, $ex, $count);
    }

    /**
     * @inheritDoc
     */
    public function warnOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, bool $count = true)
    {
        return $this->write(self::LEVEL_WARNING, $msg1, $msg2, true, $ex, $count);
    }

    /**
     * @inheritDoc
     */
    public function group(string $msg1, ?string $msg2 = null, ?string $endMsg1 = null, ?string $endMsg2 = null)
    {
        $this->State->Groups++;
        $this->State->GroupMessages[] = [$endMsg1 ?? ($endMsg2 === null ? null : ''), $endMsg2];
        return $this->write(self::LEVEL_NOTICE, $msg1, $msg2, false, null, true, self::TYPE_GROUP_START);
    }

    /**
     * @inheritDoc
     */
    public function groupEnd()
    {
        [$msg1, $msg2] = array_pop($this->State->GroupMessages) ?? [null, null];
        if ($msg1 !== null) {
            $this->write(self::LEVEL_NOTICE, $msg1, $msg2, false, null, true, self::TYPE_GROUP_END);
        }
        if (
            !$this->State->LastWriteWasEmptyGroupEnd
            && ($targets = $this->getTargets(self::LEVEL_NOTICE, self::TARGET_STDIO | self::TARGET_TTY))
        ) {
            $targets = [self::LEVEL_NOTICE => $targets];
            $this->write(self::LEVEL_NOTICE, '', null, false, null, false, self::TYPE_UNFORMATTED, $targets);
            $this->State->LastWriteWasEmptyGroupEnd = true;
        }
        if ($this->State->Groups > -1) {
            $this->State->Groups--;
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function info(string $msg1, ?string $msg2 = null)
    {
        return $this->write(self::LEVEL_NOTICE, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function infoOnce(string $msg1, ?string $msg2 = null)
    {
        return $this->write(self::LEVEL_NOTICE, $msg1, $msg2, true);
    }

    /**
     * @inheritDoc
     */
    public function log(string $msg1, ?string $msg2 = null)
    {
        return $this->write(self::LEVEL_INFO, $msg1, $msg2);
    }

    /**
     * @inheritDoc
     */
    public function logOnce(string $msg1, ?string $msg2 = null)
    {
        return $this->write(self::LEVEL_INFO, $msg1, $msg2, true);
    }

    /**
     * @inheritDoc
     */
    public function logProgress(string $msg1, ?string $msg2 = null)
    {
        if ($msg2 === null || $msg2 === '') {
            $msg1 = rtrim($msg1, "\r") . "\r";
        } else {
            $msg2 = rtrim($msg2, "\r") . "\r";
        }
        return $this->write(self::LEVEL_INFO, $msg1, $msg2, false, null, false, self::TYPE_PROGRESS, $this->State->TtyTargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function clearProgress()
    {
        return $this->write(self::LEVEL_INFO, "\r", null, false, null, false, self::TYPE_UNFORMATTED, $this->State->TtyTargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function debug(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0)
    {
        return $this->doDebug($msg1, $msg2, $ex, $depth);
    }

    /**
     * @inheritDoc
     */
    public function debugOnce(string $msg1, ?string $msg2 = null, ?Throwable $ex = null, int $depth = 0)
    {
        return $this->doDebug($msg1, $msg2, $ex, $depth, true);
    }

    /**
     * @return $this
     */
    private function doDebug(string $msg1, ?string $msg2, ?Throwable $ex, int $depth, bool $once = false)
    {
        $this->Facade === null || $depth++;
        if ($msg1 !== '') {
            $msg1 = " __{$msg1}__";
        }
        $msg1 = '{' . implode('', Debug::getCaller($depth + 1)) . '}' . $msg1;
        return $this->write(self::LEVEL_DEBUG, $msg1, $msg2, $once, $ex);
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
        return $this->write($level, $msg1, $msg2, false, $ex, $count, $type);
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
        return $this->write($level, $msg1, $msg2, true, $ex, $count, $type);
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
        $addLine = $level <= self::LEVEL_ERROR || Env::getDebug();
        $msg1 = $this->escape(Get::basename(get_class($exception))) . ':';
        $ex = $exception;
        $msg2 = '';
        do {
            if ($ex !== $exception) {
                $msg2 .= sprintf(
                    "\nCaused by __%s__: ",
                    $this->escape(Get::basename(get_class($ex))),
                );
            }
            $msg2 .= $this->escape(
                $ex instanceof MultipleErrorException && !$ex->hasUnreportedErrors()
                    ? $ex->getMessageOnly()
                    : $ex->getMessage()
            );
            if ($addLine) {
                $msg2 .= sprintf(
                    ' ~~in %s:%d~~',
                    $this->escape($ex->getFile()),
                    $ex->getLine(),
                );
            }
        } while ($ex = $ex->getPrevious());

        $this->State->Msg2HasTags = true;
        try {
            $this->write($level, $msg1, $msg2, false, $exception, $count);
        } finally {
            $this->State->Msg2HasTags = false;
        }

        if ($traceLevel !== null) {
            $this->write($traceLevel, 'Stack trace:', "\n" . $exception->getTraceAsString());
            if ($exception instanceof Exception) {
                foreach ($exception->getMetadata() as $key => $value) {
                    $this->write($traceLevel, $key . ':', "\n" . rtrim((string) $value, "\n"));
                }
            }
        }

        return $this;
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
        $errors = $this->State->Errors;
        $warnings = $this->State->Warnings;
        $hasErrors = $errors || $warnings;
        $msg[] = rtrim($finishedText);
        if (!$hasErrors) {
            $msg[] = $successText;
        } elseif (!$withoutErrorsAndWarnings) {
            $msg[] = 'with ' . Inflect::format($errors, '{{#}} {{#:error}}')
                . ($warnings
                    ? ' and ' . Inflect::format($warnings, '{{#}} {{#:warning}}')
                    : '');
        }
        if ($withResourceUsage) {
            /** @var float */
            $requestTime = $_SERVER['REQUEST_TIME_FLOAT'];
            $msg[] = sprintf(
                'in %.3fs (%s memory used)',
                microtime(true) - $requestTime,
                Format::bytes(memory_get_peak_usage()),
            );
        }

        return $this->write(
            !$hasErrors || $withoutErrorsAndWarnings || $withGenericType
                ? self::LEVEL_INFO
                : ($errors ? self::LEVEL_ERROR : self::LEVEL_WARNING),
            Arr::implode(' ', $msg, ''),
            null,
            false,
            null,
            false,
            ($hasErrors && $withoutErrorsAndWarnings) || $withGenericType
                ? self::TYPE_SUMMARY
                : ($hasErrors ? self::TYPE_FAILURE : self::TYPE_SUCCESS),
        );
    }

    /**
     * @inheritDoc
     */
    public function print(string $msg, int $level = Console::LEVEL_INFO)
    {
        return $this->write($level, $msg, null, false, null, false, self::TYPE_UNFORMATTED);
    }

    /**
     * @inheritDoc
     */
    public function printStdio(string $msg, int $level = Console::LEVEL_INFO)
    {
        return $this->write($level, $msg, null, false, null, false, self::TYPE_UNFORMATTED, $this->State->StdioTargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function printTty(string $msg, int $level = Console::LEVEL_INFO)
    {
        return $this->write($level, $msg, null, false, null, false, self::TYPE_UNFORMATTED, $this->State->TtyTargetsByLevel);
    }

    /**
     * @inheritDoc
     */
    public function printStdout(string $msg, int $level = Console::LEVEL_INFO)
    {
        $targets = [$level => [$this->getStdoutTarget()]];
        return $this->write($level, $msg, null, false, null, false, self::TYPE_UNFORMATTED, $targets);
    }

    /**
     * @inheritDoc
     */
    public function count(int $level)
    {
        if ($level <= self::LEVEL_ERROR) {
            $this->State->Errors++;
        } elseif ($level === self::LEVEL_WARNING) {
            $this->State->Warnings++;
        }
        return $this;
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
     * @return $this
     */
    protected function deregisterStdioTargets()
    {
        foreach ($this->filterTargets(self::TARGET_STDIO) as $target) {
            $this->deregisterTarget($target);
        }
        return $this;
    }

    /**
     * @param int-mask-of<self::TARGET_*> $flags
     * @param array<int,TargetInterface>|null $targets
     * @return ($flags is int<1,31> ? array<int,StreamTargetInterface> : array<int,TargetInterface>)
     */
    protected function filterTargets(int $flags, ?array $targets = null): array
    {
        $targets ??= $this->State->Targets;
        $invert = false;
        if ($flags & self::TARGET_INVERT) {
            $flags &= ~self::TARGET_INVERT;
            $invert = true;
        }
        if (!$flags) {
            return $targets;
        }
        foreach ($targets as $id => $target) {
            if (
                $this->State->TargetFlags[$id] & $flags
                xor $invert
            ) {
                $filtered[$id] = $target;
            }
        }
        return $filtered ?? [];
    }

    /**
     * @template T of TargetInterface
     *
     * @param Console::LEVEL_* $level
     * @param array<Console::LEVEL_*,array<int,T>>|null $targets
     * @param Console::TYPE_* $type
     * @param-out ($targets is null ? null : array<Console::LEVEL_*,array<int,T>>) $targets
     * @return $this
     */
    protected function write(
        int $level,
        string $msg1,
        ?string $msg2,
        bool $once = false,
        ?Throwable $ex = null,
        bool $count = true,
        int $type = self::TYPE_STANDARD,
        ?array &$targets = null
    ) {
        if ($count && $level <= self::LEVEL_WARNING) {
            $this->count($level);
        }

        if ($once) {
            $hash = Get::hash(implode("\0", [$level, $msg1, $msg2, $type]));
            if (isset($this->State->Written[$hash])) {
                return $this;
            }
            $this->State->Written[$hash] = true;
        }

        if (!$this->State->Targets && !$targets) {
            $this->registerStderrTarget();
            $this->registerTarget(StreamTarget::fromFile(sprintf(
                '%s/%s-%s-%s.log',
                Sys::getTempDir(),
                Sys::getProgramBasename(),
                Get::hash(File::realpath(Sys::getProgramName())),
                Sys::getUserId(),
            )), Env::getDebug() ? self::LEVELS_ALL : self::LEVELS_ALL_EXCEPT_DEBUG);
        }

        $this->State->LastWriteWasEmptyGroupEnd = false;

        if ($msg2 === '') {
            $msg2 = null;
            $msg2HasNewline = false;
        } else {
            $msg2HasNewline = $msg2 !== null && strpos($msg2, "\n") !== false;
            if ($msg2HasNewline) {
                $msg2 = "\n" . ltrim($msg2);
            }
        }

        // PSR-3 Section 1.3: "If an Exception object is passed in the context
        // data, it MUST be in the 'exception' key."
        $context = $ex ? ['exception' => $ex] : [];
        $groupIndent = max(0, $this->State->Groups * 2);
        $msg1HasNewline = $msg1 !== '' && strpos($msg1, "\n") !== false;
        $_targets = $targets ?? $this->State->TargetsByLevel;
        foreach ($_targets[$level] ?? [] as $target) {
            $formatter = $target->getFormatter();
            $prefixWidth = mb_strlen($formatter->getMessagePrefix($level, $type));
            $indent = $groupIndent + (
                $msg1HasNewline || $prefixWidth < 4
                    ? $prefixWidth
                    : $prefixWidth - 4
            );
            $_msg1 = $msg1 === '' ? '' : $formatter->format($msg1);
            if ($indent && $msg1HasNewline) {
                $_msg1 = str_replace("\n", "\n" . str_repeat(' ', $indent), $_msg1);
            }
            if ($msg2 === null) {
                $_msg2 = null;
            } else {
                $_msg2 = $this->State->Msg2HasTags ? $formatter->format($msg2) : $msg2;
                if ($msg2HasNewline) {
                    $_msg2 = str_replace("\n", "\n" . str_repeat(' ', $indent + 2), $_msg2);
                } elseif ($_msg1 !== '') {
                    $_msg2 = ' ' . $_msg2;
                }
            }
            $message = $formatter->formatMessage($_msg1, $_msg2, $level, $type);
            if ($groupIndent && $message !== '') {
                $message = str_repeat(' ', $groupIndent) . $message;
            }
            $target->write($level, $message, $context);
        }

        return $this;
    }

    /**
     * Get an instance that is not running behind a facade
     *
     * @return static
     */
    protected function getReturnable()
    {
        return $this->Facade === null
            ? $this
            : $this->withoutFacade($this->Facade, false);
    }
}

/**
 * @internal
 */
final class ConsoleState
{
    /** @var array<int,TargetInterface> */
    public array $Targets = [];
    /** @var array<int,int-mask-of<Console::TARGET_*>> */
    public array $TargetFlags = [];
    /** @var array<Console::LEVEL_*,array<int,TargetInterface>> */
    public array $TargetsByLevel = [];
    /** @var array<Console::LEVEL_*,array<int,StreamTargetInterface>> */
    public array $StdioTargetsByLevel = [];
    /** @var array<Console::LEVEL_*,array<int,StreamTargetInterface>> */
    public array $TtyTargetsByLevel = [];
    public ?StreamTargetInterface $StdoutTarget = null;
    public ?StreamTargetInterface $StderrTarget = null;
    /** @var array<string,true> */
    public array $Written = [];
    public int $Groups = -1;
    /** @var array<array{string|null,string|null}> */
    public array $GroupMessages = [];
    public bool $LastWriteWasEmptyGroupEnd = false;
    public bool $Msg2HasTags = false;
    public int $Errors = 0;
    public int $Warnings = 0;
    public LoggerInterface $Logger;

    private function __clone() {}
}
