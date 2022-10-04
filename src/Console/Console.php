<?php

declare(strict_types=1);

namespace Lkrms\Console;

use Lkrms\Console\ConsoleLevel as Level;
use Lkrms\Console\Target\ConsoleTarget;
use Lkrms\Console\Target\StreamTarget;
use Lkrms\Facade\Env;
use Lkrms\Facade\File;
use Throwable;

/**
 * Log messages of various types to various targets
 *
 */
final class Console extends ConsoleWriter
{
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
    private $TtyTargets = [];

    /**
     * Message level => ConsoleTarget[]
     *
     * @var array<int,ConsoleTarget[]>
     */
    private $Targets = [];

    /**
     * @return $this
     */
    public function registerTarget(ConsoleTarget $target, array $levels = ConsoleLevels::ALL_DEBUG)
    {
        if ($target->isStdout() || $target->isStderr())
        {
            $this->addTarget($target, $levels, $this->StdioTargets);
        }
        if ($target->isTty())
        {
            $this->addTarget($target, $levels, $this->TtyTargets);
        }
        $this->addTarget($target, $levels, $this->Targets);

        return $this;
    }

    private function addTarget(ConsoleTarget $target, array $levels, array & $targets)
    {
        foreach ($levels as $level)
        {
            $targets[$level][] = $target;
        }
    }

    private function registerDefaultTargets()
    {
        // Log output to `{TMPDIR}/<script_basename>-<realpath_hash>-<user_id>.log`
        $this->registerTarget(StreamTarget::fromPath(File::getStablePath(".log")), ConsoleLevels::ALL_DEBUG);
        $this->registerStdioTargets();
    }

    /**
     * Register STDOUT and STDERR as targets if running on the command line
     *
     * Returns without taking any action if a target backed by STDOUT or STDERR
     * has already been registered.
     *
     * @return $this
     */
    public function registerStdioTargets()
    {
        if (PHP_SAPI != "cli" || $this->StdioTargets)
        {
            return $this;
        }

        // Send errors and warnings to STDERR, everything else to STDOUT
        $stderrLevels = ConsoleLevels::ERRORS;
        $stdoutLevels = (Env::debug()
            ? ConsoleLevels::INFO_DEBUG
            : ConsoleLevels::INFO);
        $this->registerTarget(new StreamTarget(STDERR), $stderrLevels);
        $this->registerTarget(new StreamTarget(STDOUT), $stdoutLevels);

        return $this;
    }

    protected function write(int $level, string $msg1, ?string $msg2, string $prefix, ?Throwable $ex = null)
    {
        return $this->_write($level, $msg1, $msg2, $prefix, $ex, $this->Targets);
    }

    protected function writeTty(int $level, string $msg1, ?string $msg2, string $prefix, ?Throwable $ex = null)
    {
        return $this->_write($level, $msg1, $msg2, $prefix, $ex, $this->TtyTargets);
    }

    public function out(string $msg, int $level = Level::INFO)
    {
        return $this->_write($level, $msg, null, "", null, $this->StdioTargets);
    }

    public function tty(string $msg, int $level = Level::INFO)
    {
        return $this->_write($level, $msg, null, "", null, $this->TtyTargets);
    }

    /**
     * @return $this
     */
    private function _write(int $level, string $msg1, ?string $msg2, string $prefix, ?Throwable $ex, array & $targets)
    {
        if (!$this->Targets)
        {
            $this->registerDefaultTargets();
        }

        $margin = max(0, $this->GroupLevel) * 4;
        $indent = strlen($prefix);
        $indent = max(0, strpos($msg1, "\n") !== false ? $indent : $indent - 4);

        if ($ex)
        {
            $context = ["exception" => $ex];
        }

        /** @var ConsoleTarget $target */
        foreach ($targets[$level] ?? [] as $target)
        {
            $formatter = $target->getFormatter();
            $_msg1     = $formatter->format($msg1);
            $_msg2     = $msg2 ? $formatter->format($msg2) : null;

            if ($margin + $indent && strpos($msg1, "\n") !== false)
            {
                $_msg1 = str_replace("\n", "\n" . str_repeat(" ", $margin + $indent), $_msg1);
            }

            if ($_msg2)
            {
                $_msg2 = (strpos($msg2, "\n") !== false
                    ? str_replace("\n", "\n" . str_repeat(" ", $margin + $indent + 2), "\n" . ltrim($_msg2))
                    : " " . $_msg2);
            }

            $message = $target->getMessageFormat($level)->apply($_msg1, $_msg2, $prefix);
            $target->write($level, str_repeat(" ", $margin) . $message, $context ?? []);
        }

        return $this;
    }

}
