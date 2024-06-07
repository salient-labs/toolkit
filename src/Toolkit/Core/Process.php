<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\FileDescriptor;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\LogicException;
use Salient\Core\Exception\ProcessException;
use Salient\Core\Exception\ProcessFailedException;
use Salient\Core\Exception\ProcessTerminatedBySignalException;
use Salient\Core\Exception\ProcessTimedOutException;
use Salient\Core\Exception\RuntimeException;
use Salient\Utility\File;
use Salient\Utility\Get;
use Salient\Utility\Str;
use Salient\Utility\Sys;
use Closure;
use Throwable;

/**
 * A proc_open() process wrapper
 */
final class Process
{
    private const READY = 0;
    private const RUNNING = 1;
    private const TERMINATED = 2;

    /**
     * Microseconds to wait between process status checks
     */
    private const POLL_INTERVAL = 10000;

    /**
     * Microseconds to wait for stream activity
     *
     * When {@see Process::$UseOutputFiles} is `false` (the default on platforms
     * other than Windows), this is an upper limit because
     * {@see stream_select()} returns as soon as a status change is detected.
     */
    private const READ_INTERVAL = 200000;

    private const DEFAULT_OPTIONS = [
        'suppress_errors' => true,
        'bypass_shell' => true,
    ];

    /**
     * @var array{start_time:float,spawn_interval:float,poll_time:float,poll_count:int,read_time:float,read_count:int,stop_time:float,stop_count:int}
     */
    private const DEFAULT_STATS = [
        'start_time' => 0.0,
        'spawn_interval' => 0.0,
        'poll_time' => 0.0,
        'poll_count' => 0,
        'read_time' => 0.0,
        'read_count' => 0,
        'stop_time' => 0.0,
        'stop_count' => 0,
    ];

    private const SIGTERM = 15;
    private const SIGKILL = 9;

    /** @var string[]|string */
    private $Command;
    /** @var resource */
    private $Input;
    private bool $RewindInput;
    /** @var (Closure(FileDescriptor::OUT|FileDescriptor::ERR, string): mixed)|null */
    private ?Closure $Callback;
    private ?string $Cwd;
    /** @var array<string,string>|null */
    private ?array $Env;
    private ?float $Timeout;
    private ?int $Sec;
    private int $Usec;
    private bool $CollectOutput;
    /** @readonly */
    private bool $UseOutputFiles;
    /** @var array<string,bool>|null */
    private ?array $Options = null;
    private int $State = self::READY;
    /** @var (Closure(FileDescriptor::OUT|FileDescriptor::ERR, string): mixed)|null */
    private ?Closure $OutputCallback = null;
    private ?string $OutputDir = null;
    /** @var array<FileDescriptor::OUT|FileDescriptor::ERR,resource> */
    private array $OutputFiles;
    /** @var array<FileDescriptor::OUT|FileDescriptor::ERR,int<0,max>> */
    private array $OutputFilePos;
    /** @var int|float|null */
    private $StartTime = null;
    /** @var resource|null */
    private $Process = null;
    private bool $Stopped = false;
    /** @var array<FileDescriptor::OUT|FileDescriptor::ERR,resource> */
    private array $Pipes;
    /** @var array{command:string,pid:int,running:bool,signaled:bool,stopped:bool,exitcode:int,termsig:int,stopsig:int} */
    private array $ProcessStatus;
    private int $Pid;
    private int $ExitStatus;
    /** @var int|float|null */
    private $LastPollTime = null;
    /** @var int|float|null */
    private $LastReadTime = null;
    /** @var int|float|null */
    private $LastStopTime = null;
    /** @var array<FileDescriptor::OUT|FileDescriptor::ERR,resource> */
    private array $Output = [];
    /** @var array<FileDescriptor::OUT|FileDescriptor::ERR,int<0,max>> */
    private array $OutputPos = [];
    /** @var array{start_time:float,spawn_interval:float,poll_time:float,poll_count:int,read_time:float,read_count:int,stop_time:float,stop_count:int} */
    private array $Stats = self::DEFAULT_STATS;

    /**
     * Creates a new Process object
     *
     * @param string[] $command
     * @param resource|string|null $input
     * @param (Closure(FileDescriptor::OUT|FileDescriptor::ERR $fd, string $output): mixed)|null $callback
     * @param array<string,string>|null $env
     */
    public function __construct(
        array $command,
        $input = null,
        ?Closure $callback = null,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
        bool $collectOutput = true,
        bool $useOutputFiles = false
    ) {
        $this->Command = $command;
        $this->Callback = $callback;
        $this->Cwd = $cwd;
        $this->Env = $env;
        $this->UseOutputFiles = $useOutputFiles || Sys::isWindows();
        $this->CollectOutput = $collectOutput;

        $this->applyInput($input);
        $this->applyTimeout($timeout);
    }

    /**
     * Creates a new Process object for a shell command
     *
     * @param resource|string|null $input
     * @param (Closure(FileDescriptor::OUT|FileDescriptor::ERR $fd, string $output): mixed)|null $callback
     * @param array<string,string>|null $env
     */
    public static function withShellCommand(
        string $command,
        $input = null,
        ?Closure $callback = null,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
        bool $collectOutput = true,
        bool $useOutputFiles = false
    ): self {
        $process = new self([], $input, $callback, $cwd, $env, $timeout, $collectOutput, $useOutputFiles);
        $process->Command = $command;
        $process->Options = array_diff_key(self::DEFAULT_OPTIONS, ['bypass_shell' => null]);
        return $process;
    }

    public function __destruct()
    {
        if ($this->updateStatus()->isRunning()) {
            $this->stop()->assertHasTerminated(true);
        }

        if (!$this->UseOutputFiles) {
            return;
        }

        if ($this->OutputFiles ?? null) {
            $this->closeStreams($this->OutputFiles);
        }

        if ($this->OutputDir !== null && is_dir($this->OutputDir)) {
            File::pruneDir($this->OutputDir, true);
        }
    }

    /**
     * Pass input to the process, rewinding it before each run and making it
     * seekable if necessary
     *
     * @param resource|string|null $input
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function setInput($input)
    {
        $this->assertIsNotRunning();
        $this->applyInput($input);
        return $this;
    }

    /**
     * Pass input to the process without making it seekable or rewinding it
     * before each run
     *
     * @param resource $input
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function pipeInput($input)
    {
        $this->assertIsNotRunning();
        $this->Input = $input;
        $this->RewindInput = false;
        return $this;
    }

    /**
     * Set the callback that receives output from the process
     *
     * @param (Closure(FileDescriptor::OUT|FileDescriptor::ERR $fd, string $output): mixed)|null $callback
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function setCallback(?Closure $callback)
    {
        $this->assertIsNotRunning();
        $this->Callback = $callback;
        return $this;
    }

    /**
     * Set the initial working directory of the process
     *
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function setCwd(?string $cwd)
    {
        $this->assertIsNotRunning();
        $this->Cwd = $cwd;
        return $this;
    }

    /**
     * Set the environment of the process
     *
     * @param array<string,string>|null $env
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function setEnv(?array $env)
    {
        $this->assertIsNotRunning();
        $this->Env = $env;
        return $this;
    }

    /**
     * Set the maximum number of seconds to allow the process to run
     *
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function setTimeout(?float $timeout)
    {
        $this->assertIsNotRunning();
        $this->Timeout = $timeout;
        return $this;
    }

    /**
     * Disable collection of output written to STDOUT and STDERR by the process
     *
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function disableOutputCollection()
    {
        $this->assertIsNotRunning();
        $this->CollectOutput = false;
        return $this;
    }

    /**
     * Enable collection of output written to STDOUT and STDERR by the process
     *
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function enableOutputCollection()
    {
        $this->assertIsNotRunning();
        $this->CollectOutput = true;
        return $this;
    }

    /**
     * Run the process, throwing an exception if its exit status is non-zero
     *
     * @param (Closure(FileDescriptor::OUT|FileDescriptor::ERR $fd, string $output): mixed)|null $callback
     * @return $this
     */
    public function runWithoutFail(?Closure $callback = null)
    {
        if ($this->run($callback) !== 0) {
            throw new ProcessFailedException(sprintf(
                'Process failed with exit status %d: %s',
                $this->ExitStatus,
                Get::code($this->Command),
            ));
        }

        return $this;
    }

    /**
     * Run the process and return its exit status
     *
     * @param (Closure(FileDescriptor::OUT|FileDescriptor::ERR $fd, string $output): mixed)|null $callback
     */
    public function run(?Closure $callback = null): int
    {
        return $this->start($callback)->wait();
    }

    /**
     * Run the process without waiting for it to exit
     *
     * @param (Closure(FileDescriptor::OUT|FileDescriptor::ERR $fd, string $output): mixed)|null $callback
     * @return $this
     */
    public function start(?Closure $callback = null)
    {
        $this->assertIsNotRunning();

        $this->reset();
        $this->OutputCallback = $callback ?? $this->Callback;

        if ($this->RewindInput) {
            File::rewind($this->Input);
        }

        $descriptors = [FileDescriptor::IN => $this->Input];
        $handles = [];

        if ($this->UseOutputFiles) {
            // Use files in a temporary directory to collect output. This is
            // necessary on Windows, where proc_open() blocks until the process
            // exits if standard output pipes are used, but is also useful in
            // scenarios where polling for output would be inefficient.
            $this->OutputDir ??= File::createTempDir();
            foreach ([FileDescriptor::OUT, FileDescriptor::ERR] as $fd) {
                $file = $this->OutputDir . '/' . $fd;
                $descriptors[$fd] = ['file', $file, 'w'];

                // Use streams in $this->OutputFiles to:
                //
                // - create output files before the first run
                // - truncate output files before subsequent runs
                // - service $this->getOutput() etc. during and after each run
                //   (instead of writing the same output to php://temp streams)
                if ($stream = $this->OutputFiles[$fd] ?? null) {
                    File::truncate($stream, 0, $file);
                } else {
                    $stream = File::open($file, 'w+');
                    $this->OutputFiles[$fd] = $stream;
                }
                if ($this->CollectOutput) {
                    $this->Output[$fd] = $stream;
                    $this->OutputPos[$fd] = 0;
                    $this->OutputFilePos[$fd] = 0;
                }

                // Create additional streams to tail output files for this run
                $handles[$fd] = File::open($file, 'r');
            }
        } else {
            $descriptors += [
                FileDescriptor::OUT => ['pipe', 'w'],
                FileDescriptor::ERR => ['pipe', 'w'],
            ];
            if ($this->CollectOutput) {
                $this->Output = [
                    FileDescriptor::OUT => File::open('php://temp', 'a+'),
                    FileDescriptor::ERR => File::open('php://temp', 'a+'),
                ];
                $this->OutputPos = [
                    FileDescriptor::OUT => 0,
                    FileDescriptor::ERR => 0,
                ];
            }
        }

        $this->StartTime = hrtime(true);

        $process = $this->throwOnFailure(
            @proc_open(
                $this->Command,
                $descriptors,
                $pipes,
                $this->Cwd,
                $this->Env,
                $this->Options ?? self::DEFAULT_OPTIONS,
            ),
            'Error running process: %s',
        );

        $now = hrtime(true);
        $this->Stats['start_time'] = $this->StartTime / 1000;
        $this->Stats['spawn_interval'] = ($now - $this->StartTime) / 1000;

        $pipes += $handles;

        foreach ($pipes as $pipe) {
            @stream_set_blocking($pipe, false);
        }

        $this->Process = $process;
        $this->Pipes = $pipes;
        $this->State = self::RUNNING;

        $this->updateStatus();
        $this->Pid = $this->ProcessStatus['pid'];

        return $this;
    }

    /**
     * Check the process for output and update its status
     *
     * If fewer than {@see Process::POLL_INTERVAL} microseconds have passed
     * since the process was last polled, a delay is inserted to minimise CPU
     * usage.
     *
     * @return $this
     */
    public function poll(bool $now = false)
    {
        $this->assertHasRun();

        $this->checkTimeout();
        if (!$now) {
            $this->awaitInterval($this->LastPollTime, self::POLL_INTERVAL);
        }
        $this->updateStatus();

        return $this;
    }

    /**
     * Wait for the process to exit and return its exit status
     */
    public function wait(): int
    {
        $this->assertHasRun();

        while ($this->Pipes) {
            $this->checkTimeout();
            $this->read();
            $this->updateStatus(false);
        }

        while ($this->isRunning()) {
            $this->checkTimeout();
            usleep(self::POLL_INTERVAL);
        }

        if (
            $this->ProcessStatus['signaled'] && (
                !$this->Stopped || !(
                    $this->ProcessStatus['termsig'] === self::SIGTERM
                    || $this->ProcessStatus['termsig'] === self::SIGKILL
                )
            )
        ) {
            throw new ProcessTerminatedBySignalException(sprintf(
                'Process terminated by signal %d: %s',
                $this->ProcessStatus['termsig'],
                Get::code($this->Command),
            ));
        }

        return $this->ExitStatus;
    }

    /**
     * Terminate the process if it is still running
     *
     * @return $this
     */
    public function stop(float $timeout = 10)
    {
        $this->assertHasRun();

        // Work around issue where processes do not receive signals immediately
        // after launch on some platforms (e.g. macOS + PHP 8.2.18)
        $this->awaitInterval($this->StartTime, self::POLL_INTERVAL);

        if (!$this->updateStatus()->isRunning()) {
            // @codeCoverageIgnoreStart
            return $this;
            // @codeCoverageIgnoreEnd
        }

        try {
            // Send SIGTERM first
            $this->doStop(self::SIGTERM);
            if ($this->waitForStop($this->LastStopTime + $timeout * 1000000000)) {
                return $this;
            }

            // If the process doesn't stop, fall back to SIGKILL
            $this->doStop(self::SIGKILL);
            if ($this->waitForStop($this->LastStopTime + 1000000000)) {
                return $this;
            }
        } catch (ProcessException $ex) {
            // Ignore the exception if the process is no longer running
            if (!$this->updateStatus()->isRunning()) {
                return $this;
            }
            throw $ex;
        }

        throw new ProcessException(sprintf(
            'Process could not be stopped: %s',
            Get::code($this->Command),
        ));
    }

    /**
     * Check if the process is running
     *
     * @phpstan-impure
     *
     * @phpstan-assert-if-true !null $this->Process
     */
    public function isRunning(): bool
    {
        return
            $this->State === self::RUNNING
            && $this->maybeUpdateStatus()->State === self::RUNNING;
    }

    /**
     * Check if the process ran and terminated
     */
    public function isTerminated(): bool
    {
        return
            $this->State === self::TERMINATED
            || $this->maybeUpdateStatus()->State === self::TERMINATED;
    }

    /**
     * Check if the process ran and was terminated by a signal
     */
    public function isTerminatedBySignal(): bool
    {
        return $this->isTerminated()
            && $this->ProcessStatus['signaled'];
    }

    /**
     * Get the command passed to proc_open() to spawn the process
     *
     * @return string[]|string
     */
    public function getCommand()
    {
        return $this->Command;
    }

    /**
     * Get the process ID of the spawned process
     *
     * @throws LogicException if the process has not run.
     */
    public function getPid(): int
    {
        $this->assertHasRun();

        return $this->Pid;
    }

    /**
     * Get output written to STDOUT or STDERR by the process since it started
     *
     * @param FileDescriptor::OUT|FileDescriptor::ERR $fd
     * @throws LogicException if the process has not run or if output
     * collection is disabled.
     */
    public function getOutput(int $fd = FileDescriptor::OUT): string
    {
        return $this->doGetOutput($fd, false, false);
    }

    /**
     * Get output written to STDOUT or STDERR by the process since it was last
     * read
     *
     * @param FileDescriptor::OUT|FileDescriptor::ERR $fd
     * @throws LogicException if the process has not run or if output
     * collection is disabled.
     */
    public function getNewOutput(int $fd = FileDescriptor::OUT): string
    {
        return $this->doGetOutput($fd, false, true);
    }

    /**
     * Get text written to STDOUT or STDERR by the process since it started
     *
     * @param FileDescriptor::OUT|FileDescriptor::ERR $fd
     * @throws LogicException if the process has not run or if output
     * collection is disabled.
     */
    public function getText(int $fd = FileDescriptor::OUT): string
    {
        return $this->doGetOutput($fd, true, false);
    }

    /**
     * Get text written to STDOUT or STDERR by the process since it was last
     * read
     *
     * @param FileDescriptor::OUT|FileDescriptor::ERR $fd
     * @throws LogicException if the process has not run or if output
     * collection is disabled.
     */
    public function getNewText(int $fd = FileDescriptor::OUT): string
    {
        return $this->doGetOutput($fd, true, true);
    }

    /**
     * @param FileDescriptor::OUT|FileDescriptor::ERR $fd
     */
    private function doGetOutput(int $fd, bool $text, bool $new): string
    {
        $this->assertHasRun();

        if (!$this->Output) {
            throw new LogicException('Output not collected');
        }

        $stream = $this->updateStatus()->Output[$fd];
        $offset = $new
            ? $this->OutputPos[$fd]
            : ($this->UseOutputFiles
                ? $this->OutputFilePos[$fd]
                : 0);
        $output = File::getContents($stream, $offset);
        /** @var int<0,max> */
        $pos = File::tell($stream);
        $this->OutputPos[$fd] = $pos;
        return $text
            ? Str::trimNativeEol($output)
            : $output;
    }

    /**
     * Forget output written by the process
     *
     * @return $this
     */
    public function clearOutput()
    {
        if (!$this->Output) {
            return $this;
        }

        foreach ([FileDescriptor::OUT, FileDescriptor::ERR] as $fd) {
            $stream = $this->Output[$fd];
            if ($this->UseOutputFiles) {
                /** @var int<0,max> */
                $pos = File::tell($stream);
                $this->OutputFilePos[$fd] = $pos;
            } else {
                File::truncate($stream);
            }
            $this->OutputPos[$fd] = 0;
        }

        return $this;
    }

    /**
     * Get the exit status of the process
     *
     * @throws ProcessException if the process has not terminated.
     */
    public function getExitStatus(): int
    {
        $this->assertHasTerminated();

        return $this->ExitStatus;
    }

    /**
     * Get process statistics
     *
     * @return array{start_time:float,spawn_interval:float,poll_time:float,poll_count:int,read_time:float,read_count:int,stop_time:float,stop_count:int}
     */
    public function getStats(): array
    {
        $this->assertHasRun();

        return $this->Stats;
    }

    private function reset(): void
    {
        $this->OutputCallback = null;
        unset($this->OutputFilePos);
        $this->StartTime = null;
        $this->Process = null;
        $this->Stopped = false;
        unset($this->Pipes);
        unset($this->ProcessStatus);
        unset($this->Pid);
        unset($this->ExitStatus);
        $this->LastPollTime = null;
        $this->LastReadTime = null;
        $this->LastStopTime = null;
        $this->Output = [];
        $this->OutputPos = [];
        $this->Stats = self::DEFAULT_STATS;
    }

    /**
     * @return $this
     */
    private function maybeUpdateStatus()
    {
        if (!$this->checkInterval($this->LastPollTime, self::POLL_INTERVAL)) {
            return $this;
        }
        return $this->updateStatus();
    }

    /**
     * @return $this
     */
    private function updateStatus(bool $read = true, bool $wait = false)
    {
        if ($this->Process === null) {
            return $this;
        }

        $now = hrtime(true);

        $this->ProcessStatus = $this->throwOnFailure(
            @proc_get_status($this->Process),
            'Error getting process status: %s',
        );

        $this->LastPollTime = $now;
        $this->Stats['poll_time'] = $now / 1000;
        $this->Stats['poll_count']++;

        $running = $this->ProcessStatus['running'];

        if ($read || !$running) {
            $this->read($running && $wait, !$running);
        }

        if (!$running) {
            // In the unlikely event that any pipes remain open, close them
            // before closing the process
            if ($this->Pipes) {
                // @codeCoverageIgnoreStart
                $this->closeStreams($this->Pipes);
                // @codeCoverageIgnoreEnd
            }

            if (is_resource($this->Process)) {
                // The return value of `proc_close()` is not reliable, so ignore
                // it and use `error_get_last()` to check for errors
                error_clear_last();
                @proc_close($this->Process);
                $error = error_get_last();
                if ($error !== null) {
                    // @codeCoverageIgnoreStart
                    $this->throw('Error closing process: %s', $error);
                    // @codeCoverageIgnoreEnd
                }
            }

            $this->Process = null;
            $this->State = self::TERMINATED;
            $this->ExitStatus = $this->ProcessStatus['exitcode'];

            if (
                $this->ExitStatus === -1
                && $this->ProcessStatus['signaled']
                && $this->ProcessStatus['termsig'] > 0
            ) {
                $this->ExitStatus = 128 + $this->ProcessStatus['termsig'];
            }
        }

        return $this;
    }

    private function read(bool $wait = true, bool $close = false): void
    {
        if (!$this->Pipes) {
            return;
        }

        $now = hrtime(true);
        $read = $this->Pipes;

        if ($this->UseOutputFiles) {
            if ($wait) {
                $interval = $this->Usec === 0
                    ? self::READ_INTERVAL
                    : $this->Usec;
                $this->awaitInterval($this->LastReadTime, $interval);
            }
        } else {
            $write = null;
            $except = null;
            $sec = $wait ? $this->Sec : 0;
            $usec = $wait ? $this->Usec : 0;

            $this->throwOnFailure(
                @stream_select($read, $write, $except, $sec, $usec),
                'Error checking for process output: %s',
            );
        }

        foreach ($read as $i => $pipe) {
            $data = $this->throwOnFailure(
                @stream_get_contents($pipe),
                'Error reading process output: %s',
            );

            if ($data !== '') {
                if ($this->CollectOutput && !$this->UseOutputFiles) {
                    File::write($this->Output[$i], $data);
                }
                if ($this->OutputCallback) {
                    ($this->OutputCallback)($i, $data);
                }
            }

            error_clear_last();
            if ((!$this->UseOutputFiles || $close) && @feof($pipe)) {
                $error = error_get_last();
                if ($error !== null) {
                    // @codeCoverageIgnoreStart
                    $this->throw('Error reading process output: %s', $error);
                    // @codeCoverageIgnoreEnd
                }
                File::close($pipe);
                unset($this->Pipes[$i]);
            }
        }

        $this->LastReadTime = $now;
        $this->Stats['read_time'] = $now / 1000;
        $this->Stats['read_count']++;
    }

    /**
     * @return $this
     * @throws ProcessTimedOutException if the process timed out.
     */
    private function checkTimeout()
    {
        if (
            $this->State !== self::RUNNING
            || $this->Timeout === null
            || $this->Timeout > (hrtime(true) - $this->StartTime) / 1000000000
        ) {
            return $this;
        }

        try {
            $this->stop();
            // @codeCoverageIgnoreStart
        } catch (Throwable $ex) {
            throw new ProcessException(sprintf(
                'Error terminating process that timed out after %.3fs: %s',
                $this->Timeout,
                Get::code($this->Command),
            ), $ex);
            // @codeCoverageIgnoreEnd
        }

        throw new ProcessTimedOutException(sprintf(
            'Process timed out after %.3fs: %s',
            $this->Timeout,
            Get::code($this->Command),
        ));
    }

    /**
     * Wait until at least $interval microseconds have passed since the given
     * time
     *
     * @param int|float|null $time
     * @return $this
     */
    private function awaitInterval($time, int $interval)
    {
        if ($time === null) {
            return $this;
        }
        $now = hrtime(true);
        $usec = (int) ($interval - ($now - $time) / 1000);
        if ($usec > 0) {
            usleep($usec);
        }
        return $this;
    }

    /**
     * Check if at least $interval microseconds have passed since the given time
     *
     * @param int|float|null $time
     */
    private function checkInterval($time, int $interval): bool
    {
        if ($time === null) {
            return true;
        }
        $now = hrtime(true);
        return (int) ($interval - ($now - $time) / 1000) <= 0;
    }

    private function doStop(int $signal): void
    {
        if ($this->Process === null) {
            return;
        }

        $now = hrtime(true);

        $this->throwOnFailure(
            @proc_terminate($this->Process, $signal),
            'Error terminating process: %s',
        );

        $this->LastStopTime = $now;
        $this->Stats['stop_count']++;
        if (!$this->Stopped) {
            $this->Stats['stop_time'] = $now / 1000;
            $this->Stopped = true;
        }
    }

    private function waitForStop(float $until): bool
    {
        do {
            usleep(self::POLL_INTERVAL);
            if (!$this->isRunning()) {
                return true;
            }
        } while (hrtime(true) < $until);

        return false;
    }

    /**
     * @param array<FileDescriptor::*,resource> $streams
     */
    private function closeStreams(array &$streams): void
    {
        foreach ($streams as $stream) {
            if (is_resource($stream)) {
                File::close($stream);
            }
        }
        $streams = [];
    }

    private function assertIsNotRunning(): void
    {
        if ($this->State === self::RUNNING) {
            throw new LogicException('Process is running');
        }
    }

    private function assertHasRun(): void
    {
        if ($this->State === self::READY) {
            throw new LogicException('Process has not run');
        }
    }

    private function assertHasTerminated(bool $runtime = false): void
    {
        if ($this->State !== self::TERMINATED) {
            $exception = $runtime
                ? RuntimeException::class
                : LogicException::class;
            throw new $exception('Process has not terminated');
        }
    }

    /**
     * @param resource|string|null $input
     */
    private function applyInput($input): void
    {
        $this->Input = $input === null || is_string($input)
            ? Str::toStream((string) $input)
            : File::getSeekableStream($input);
        $this->RewindInput = true;
    }

    private function applyTimeout(?float $timeout): void
    {
        if ($timeout !== null && $timeout <= 0) {
            throw new InvalidArgumentException(
                sprintf('Invalid timeout: %.3fs', $timeout)
            );
        }

        $this->Timeout = $timeout;
        [$this->Sec, $this->Usec] = $timeout === null
            ? [null, 0]
            : [0, min((int) ($timeout * 1000000), self::READ_INTERVAL)];
    }

    /**
     * @template T
     *
     * @param T $result
     * @return (T is false ? never : T)
     * @phpstan-param T|false $result
     * @phpstan-return ($result is false ? never : T)
     */
    private function throwOnFailure($result, string $message)
    {
        if ($result === false) {
            $this->throw($message);
        }
        return $result;
    }

    /**
     * @param array{message:string,...}|null $error
     * @return never
     */
    private function throw(string $message, ?array $error = null): void
    {
        $error ??= error_get_last();
        if ($error !== null) {
            throw new ProcessException($error['message']);
        }

        // @codeCoverageIgnoreStart
        throw new ProcessException(
            sprintf($message, Get::code($this->Command))
        );
        // @codeCoverageIgnoreEnd
    }
}
