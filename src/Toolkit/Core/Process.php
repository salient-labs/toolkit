<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Catalog\HasFileDescriptor;
use Salient\Core\Exception\ProcessDidNotTerminateException;
use Salient\Core\Exception\ProcessException;
use Salient\Core\Exception\ProcessFailedException;
use Salient\Core\Exception\ProcessTerminatedBySignalException;
use Salient\Core\Exception\ProcessTimedOutException;
use Salient\Utility\Arr;
use Salient\Utility\File;
use Salient\Utility\Str;
use Salient\Utility\Sys;
use Closure;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * @api
 */
final class Process implements HasFileDescriptor
{
    private const READY = 0;
    private const RUNNING = 1;
    private const TERMINATED = 2;

    /**
     * Microseconds to wait between process status checks
     */
    private const POLL_INTERVAL = 10000;

    /**
     * Microseconds to wait for stream activity (upper limit)
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

    /** @var list<string>|string */
    private $Command;
    /** @var resource */
    private $Input;
    private bool $RewindOnStart;
    /** @var (Closure(self::STDOUT|self::STDERR, string): mixed)|null */
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

    // --

    private int $State = self::READY;
    /** @var (Closure(self::STDOUT|self::STDERR, string): mixed)|null */
    private ?Closure $CurrentCallback = null;
    private ?string $OutputDir = null;
    /** @var array<self::STDOUT|self::STDERR,resource> */
    private array $OutputFiles;
    /** @var array<self::STDOUT|self::STDERR,int<0,max>> */
    private array $OutputFilePos;
    /** @var int|float|null */
    private $StartTime = null;
    /** @var resource|null */
    private $Process = null;
    private bool $Stopped = false;
    /** @var array<self::STDOUT|self::STDERR,resource> */
    private array $Pipes;
    /** @var array{command:string,pid:int,running:bool,signaled:bool,stopped:bool,exitcode:int,termsig:int,stopsig:int} */
    private array $ProcessStatus;
    private int $Pid;
    private int $ExitStatus;
    /** @var int|float|null */
    private $LastPollTime = null;
    /** @var int|float|null */
    private $LastReadTime = null;
    /** @var array<self::STDOUT|self::STDERR,resource> */
    private array $Output = [];
    /** @var array<self::STDOUT|self::STDERR,int<0,max>> */
    private array $OutputPos = [];
    /** @var array{start_time:float,spawn_interval:float,poll_time:float,poll_count:int,read_time:float,read_count:int,stop_time:float,stop_count:int} */
    private array $Stats = self::DEFAULT_STATS;

    /**
     * @api
     *
     * @param list<string> $command
     * @param resource|string|null $input Copied to a seekable stream if not
     * already seekable, then rewound before each run.
     * @param (Closure(Process::STDOUT|Process::STDERR $fd, string $output): mixed)|null $callback
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
        $this->CollectOutput = $collectOutput;
        $this->UseOutputFiles = $useOutputFiles || Sys::isWindows();
        $this->applyInput($input);
        $this->applyTimeout($timeout);
    }

    /**
     * Get a new process for a shell command
     *
     * @param resource|string|null $input Copied to a seekable stream if not
     * already seekable, then rewound before each run.
     * @param (Closure(Process::STDOUT|Process::STDERR $fd, string $output): mixed)|null $callback
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
        $process->Options = Arr::unset(self::DEFAULT_OPTIONS, 'bypass_shell');
        return $process;
    }

    /**
     * @internal
     */
    public function __destruct()
    {
        if ($this->updateStatus()->isRunning()) {
            $this->stop();
        }
        if ($this->UseOutputFiles) {
            if ($this->OutputFiles ?? null) {
                $this->closeStreams($this->OutputFiles);
            }
            if ($this->OutputDir !== null && is_dir($this->OutputDir)) {
                File::pruneDir($this->OutputDir, true);
            }
        }
    }

    /**
     * Set the input passed to the process
     *
     * @param resource|string|null $input Copied to a seekable stream if not
     * already seekable, then rewound before each run.
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function setInput($input): self
    {
        $this->assertIsNotRunning();
        $this->applyInput($input);
        return $this;
    }

    /**
     * @param resource|string|null $input
     */
    private function applyInput($input): void
    {
        $this->Input = $input === null || is_string($input)
            ? Str::toStream((string) $input)
            : File::getSeekableStream($input);
        $this->RewindOnStart = true;
    }

    /**
     * Pass input directly to the process
     *
     * @param resource $input
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function pipeInput($input): self
    {
        $this->assertIsNotRunning();
        $this->Input = $input;
        $this->RewindOnStart = false;
        return $this;
    }

    /**
     * Set the callback that receives output from the process
     *
     * @param (Closure(Process::STDOUT|Process::STDERR $fd, string $output): mixed)|null $callback
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function setCallback(?Closure $callback): self
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
    public function setCwd(?string $cwd): self
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
    public function setEnv(?array $env): self
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
    public function setTimeout(?float $timeout): self
    {
        $this->assertIsNotRunning();
        $this->applyTimeout($timeout);
        return $this;
    }

    private function applyTimeout(?float $timeout): void
    {
        if ($timeout !== null && $timeout <= 0) {
            throw new InvalidArgumentException(sprintf(
                'Invalid timeout: %.3fs',
                $timeout,
            ));
        }

        $this->Timeout = $timeout;
        [$this->Sec, $this->Usec] = $timeout === null
            ? [null, 0]
            : [0, min((int) ($timeout * 1000000), self::READ_INTERVAL)];
    }

    /**
     * Disable collection of output written to STDOUT and STDERR by the process
     *
     * @return $this
     * @throws LogicException if the process is running.
     */
    public function disableOutputCollection(): self
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
    public function enableOutputCollection(): self
    {
        $this->assertIsNotRunning();
        $this->CollectOutput = true;
        return $this;
    }

    /**
     * Run the process and throw an exception if its exit status is non-zero
     *
     * @param (Closure(Process::STDOUT|Process::STDERR $fd, string $output): mixed)|null $callback
     * @return $this
     * @throws LogicException if the process is running.
     * @throws ProcessTimedOutException if the process times out.
     * @throws ProcessTerminatedBySignalException if the process is terminated
     * by an uncaught signal.
     * @throws ProcessFailedException if the process returns a non-zero exit
     * status.
     */
    public function runWithoutFail(?Closure $callback = null): self
    {
        if ($this->run($callback) !== 0) {
            throw new ProcessFailedException(
                'Process failed with exit status %d: %s',
                [$this->ExitStatus, $this],
            );
        }
        return $this;
    }

    /**
     * Run the process and return its exit status
     *
     * @param (Closure(Process::STDOUT|Process::STDERR $fd, string $output): mixed)|null $callback
     * @throws LogicException if the process is running.
     * @throws ProcessTimedOutException if the process times out.
     * @throws ProcessTerminatedBySignalException if the process is terminated
     * by an uncaught signal.
     */
    public function run(?Closure $callback = null): int
    {
        return $this->start($callback)->wait();
    }

    /**
     * Start the process in the background
     *
     * @param (Closure(Process::STDOUT|Process::STDERR $fd, string $output): mixed)|null $callback
     * @return $this
     * @throws LogicException if the process is running.
     * @throws ProcessTerminatedBySignalException if the process is terminated
     * by an uncaught signal.
     */
    public function start(?Closure $callback = null): self
    {
        $this->assertIsNotRunning();

        $this->reset();
        $this->CurrentCallback = $callback ?? $this->Callback;

        if ($this->RewindOnStart) {
            File::rewind($this->Input);
        }
        $descriptors = [self::STDIN => $this->Input];
        $handles = [];

        if ($this->UseOutputFiles) {
            // Use files in a temporary directory to collect output (necessary
            // on Windows, where `proc_open()` blocks until the process exits if
            // standard output pipes are used, and useful in scenarios where
            // polling for output would be inefficient)
            $this->OutputDir ??= File::createTempDir();
            foreach ([self::STDOUT, self::STDERR] as $fd) {
                $file = $this->OutputDir . '/' . $fd;
                $descriptors[$fd] = ['file', $file, 'w'];
                $stream = $this->OutputFiles[$fd] ?? null;
                // Create output files before the first run, and truncate them
                // before subsequent runs
                if (!$stream) {
                    $stream = File::open($file, 'w+');
                    $this->OutputFiles[$fd] = $stream;
                } else {
                    File::truncate($stream, 0, $file);
                }
                if ($this->CollectOutput) {
                    $this->Output[$fd] = $stream;
                    $this->OutputPos[$fd] = 0;
                    $this->OutputFilePos[$fd] = 0;
                }
                // Tail output files separately
                $handles[$fd] = File::open($file, 'r');
            }
        } else {
            $descriptors += [
                self::STDOUT => ['pipe', 'w'],
                self::STDERR => ['pipe', 'w'],
            ];
            if ($this->CollectOutput) {
                $this->Output = [
                    self::STDOUT => File::open('php://temp', 'a+'),
                    self::STDERR => File::open('php://temp', 'a+'),
                ];
                $this->OutputPos = [
                    self::STDOUT => 0,
                    self::STDERR => 0,
                ];
            }
        }

        $this->StartTime = hrtime(true);
        $this->Process = $this->check(
            @proc_open(
                $this->Command,
                $descriptors,
                $pipes,
                $this->Cwd,
                $this->Env,
                $this->Options ?? self::DEFAULT_OPTIONS,
            ),
            'Error starting process: %s',
        );

        $now = hrtime(true);
        $this->Stats['start_time'] = $this->StartTime / 1000;
        $this->Stats['spawn_interval'] = ($now - $this->StartTime) / 1000;

        $pipes += $handles;
        foreach ($pipes as $pipe) {
            @stream_set_blocking($pipe, false);
        }
        $this->Pipes = $pipes;
        $this->State = self::RUNNING;
        $this->updateStatus();
        $this->Pid = $this->ProcessStatus['pid'];
        return $this;
    }

    /**
     * Wait for the process to exit and return its exit status
     *
     * @throws LogicException if the process has not run.
     * @throws ProcessTimedOutException if the process times out.
     * @throws ProcessTerminatedBySignalException if the process is terminated
     * by an uncaught signal.
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

        return $this->ExitStatus;
    }

    /**
     * Check for output written by the process and update its status
     *
     * If fewer than {@see Process::POLL_INTERVAL} microseconds have passed
     * since the process was last polled, a delay is inserted to minimise CPU
     * usage.
     *
     * @return $this
     * @throws LogicException if the process has not run.
     * @throws ProcessTimedOutException if the process times out.
     * @throws ProcessTerminatedBySignalException if the process is terminated
     * by an uncaught signal.
     */
    public function poll(bool $now = false): self
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
     * Terminate the process if it is still running
     *
     * @return $this
     * @throws LogicException if the process has not run.
     * @throws ProcessTerminatedBySignalException if the process is terminated
     * by an uncaught signal.
     * @throws ProcessDidNotTerminateException if the process does not
     * terminate.
     */
    public function stop(float $timeout = 10): self
    {
        $this->assertHasRun();

        // Work around issue where processes don't receive signals immediately
        // after launch
        $this->awaitInterval($this->StartTime, self::POLL_INTERVAL);

        if (!$this->updateStatus()->isRunning()) {
            return $this;
        }

        try {
            // Send SIGTERM first, then SIGKILL if the process is still running
            // after `$timeout` seconds
            if (
                $this->doStop(self::SIGTERM, $timeout)
                || $this->doStop(self::SIGKILL, 1)
            ) {
                return $this;
            }
        } catch (ProcessException $ex) {
            // Ignore the exception if the process is no longer running
            // @phpstan-ignore booleanNot.alwaysFalse
            if (!$this->updateStatus()->isRunning()) {
                return $this;
            }
            throw $ex;
        }

        throw new ProcessDidNotTerminateException(
            'Process did not terminate: %s',
            [$this],
        );
    }

    private function doStop(int $signal, float $timeout): bool
    {
        if (!$this->Process) {
            return true;
        }

        $now = hrtime(true);
        $this->check(
            @proc_terminate($this->Process, $signal),
            'Error terminating process: %s',
        );
        $this->Stats['stop_count']++;
        if (!$this->Stopped) {
            $this->Stats['stop_time'] = $now / 1000;
            $this->Stopped = true;
        }

        $until = $now + $timeout * 1000000000;
        do {
            usleep(self::POLL_INTERVAL);
            if (!$this->isRunning()) {
                return true;
            }
        } while (hrtime(true) < $until);

        return false;
    }

    /**
     * Check if the process is running
     */
    public function isRunning(): bool
    {
        return $this->State === self::RUNNING
            && $this->maybeUpdateStatus()->State === self::RUNNING;
    }

    /**
     * Check if the process ran and terminated
     */
    public function isTerminated(): bool
    {
        return $this->State === self::TERMINATED
            || $this->maybeUpdateStatus()->State === self::TERMINATED;
    }

    /**
     * Get the command spawned by the process
     *
     * @return list<string>|string
     */
    public function getCommand()
    {
        return $this->Command;
    }

    /**
     * Get the process ID of the command spawned by the process
     *
     * @throws LogicException if the process has not run.
     */
    public function getPid(): int
    {
        $this->assertHasRun();
        return $this->Pid;
    }

    /**
     * Get output written to STDOUT or STDERR by the process
     *
     * @param Process::STDOUT|Process::STDERR $fd
     * @throws LogicException if the process has not run or if output collection
     * is disabled.
     */
    public function getOutput(int $fd = Process::STDOUT): string
    {
        return $this->doGetOutput($fd, false, false);
    }

    /**
     * Get output written to STDOUT or STDERR by the process since it was last
     * read
     *
     * @param Process::STDOUT|Process::STDERR $fd
     * @throws LogicException if the process has not run or if output collection
     * is disabled.
     */
    public function getNewOutput(int $fd = Process::STDOUT): string
    {
        return $this->doGetOutput($fd, false, true);
    }

    /**
     * Get text written to STDOUT or STDERR by the process
     *
     * @param Process::STDOUT|Process::STDERR $fd
     * @throws LogicException if the process has not run or if output collection
     * is disabled.
     */
    public function getOutputAsText(int $fd = Process::STDOUT): string
    {
        return $this->doGetOutput($fd, true, false);
    }

    /**
     * Get text written to STDOUT or STDERR by the process since it was last
     * read
     *
     * @param Process::STDOUT|Process::STDERR $fd
     * @throws LogicException if the process has not run or if output collection
     * is disabled.
     */
    public function getNewOutputAsText(int $fd = Process::STDOUT): string
    {
        return $this->doGetOutput($fd, true, true);
    }

    /**
     * @param self::STDOUT|self::STDERR $fd
     */
    private function doGetOutput(int $fd, bool $text, bool $new): string
    {
        $this->assertHasRun();

        if (!$this->Output) {
            throw new LogicException('Output collection disabled');
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
     * Forget output written to STDOUT and STDERR by the process
     *
     * @return $this
     */
    public function clearOutput(): self
    {
        if (!$this->Output) {
            return $this;
        }

        foreach ([self::STDOUT, self::STDERR] as $fd) {
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
     * @throws LogicException if the process has not run.
     */
    public function getStats(): array
    {
        $this->assertHasRun();
        return $this->Stats;
    }

    /**
     * @return $this
     */
    private function checkTimeout(): self
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
        } catch (Throwable $ex) {
            throw new ProcessException(
                'Error terminating process that timed out after %.3fs: %s',
                [$this->Timeout, $this],
                $ex,
            );
        }

        throw new ProcessTimedOutException(
            'Process timed out after %.3fs: %s',
            [$this->Timeout, $this],
        );
    }

    /**
     * @return $this
     */
    private function maybeUpdateStatus(): self
    {
        return $this->checkInterval($this->LastPollTime, self::POLL_INTERVAL)
            ? $this->updateStatus()
            : $this;
    }

    /**
     * @return $this
     */
    private function updateStatus(bool $read = true): self
    {
        if (!$this->Process) {
            return $this;
        }

        $now = hrtime(true);
        $process = $this->Process;
        $this->ProcessStatus = $this->check(
            @proc_get_status($process),
            'Error getting process status: %s',
        );
        $this->LastPollTime = $now;
        $this->Stats['poll_time'] = $now / 1000;
        $this->Stats['poll_count']++;

        $running = $this->ProcessStatus['running'];
        if ($read || !$running) {
            $this->read(false, !$running);
        }
        if (!$running) {
            // Close any pipes left open by `$this->read()`
            if ($this->Pipes) {
                // @codeCoverageIgnoreStart
                $this->closeStreams($this->Pipes);
                // @codeCoverageIgnoreEnd
            }

            // The return value of `proc_close()` is not reliable, so ignore it
            // and use `error_get_last()` to check for errors
            error_clear_last();
            @proc_close($process);
            if ($error = error_get_last()) {
                // @codeCoverageIgnoreStart
                $this->throw('Error closing process: %s', $error);
                // @codeCoverageIgnoreEnd
            }

            $this->ExitStatus = $this->ProcessStatus['exitcode'];
            $this->State = self::TERMINATED;
            $this->Process = null;
            if (
                $this->ExitStatus === -1
                && $this->ProcessStatus['signaled']
                && ($signal = $this->ProcessStatus['termsig']) > 0
            ) {
                $this->ExitStatus = 128 + $signal;
                if (!$this->Stopped || ([
                    self::SIGTERM => false,
                    self::SIGKILL => false,
                ][$signal] ?? true)) {
                    throw new ProcessTerminatedBySignalException(
                        'Process terminated by signal %d: %s',
                        [$signal, $this],
                    );
                }
            }
        }

        return $this;
    }

    private function read(bool $wait = true, bool $closeAtEof = false): void
    {
        if (!$this->Pipes) {
            return;
        }

        $now = hrtime(true);
        $read = $this->Pipes;
        if ($this->UseOutputFiles) {
            if ($wait) {
                $usec = $this->Usec === 0
                    ? self::READ_INTERVAL
                    : $this->Usec;
                $this->awaitInterval($this->LastReadTime, $usec);
            }
        } else {
            $write = null;
            $except = null;
            $sec = $wait ? $this->Sec : 0;
            $usec = $wait ? $this->Usec : 0;
            File::select($read, $write, $except, $sec, $usec);
        }
        foreach ($read as $i => $pipe) {
            $data = File::getContents($pipe);
            if ($data !== '') {
                if ($this->CollectOutput && !$this->UseOutputFiles) {
                    File::writeAll($this->Output[$i], $data);
                }
                if ($this->CurrentCallback) {
                    ($this->CurrentCallback)($i, $data);
                }
            }
            if ((!$this->UseOutputFiles || $closeAtEof) && File::eof($pipe)) {
                File::close($pipe);
                unset($this->Pipes[$i]);
            }
        }

        $this->LastReadTime = $now;
        $this->Stats['read_time'] = $now / 1000;
        $this->Stats['read_count']++;
    }

    /**
     * @param resource[] $streams
     * @param-out array{} $streams
     */
    private function closeStreams(array &$streams): void
    {
        foreach ($streams as $stream) {
            File::close($stream);
        }
        $streams = [];
    }

    private function reset(): void
    {
        $this->CurrentCallback = null;
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
        $this->Output = [];
        $this->OutputPos = [];
        $this->Stats = self::DEFAULT_STATS;
    }

    /**
     * Wait until at least $interval microseconds have passed since a given time
     *
     * @param int|float|null $time
     * @return $this
     */
    private function awaitInterval($time, int $interval): self
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
     * Check if at least $interval microseconds have passed since a given time
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

    /**
     * @template T
     *
     * @param T|false $result
     * @return ($result is false ? never : T)
     */
    private function check($result, string $message)
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
        if ($error) {
            throw new ProcessException($error['message']);
        }
        // @codeCoverageIgnoreStart
        throw new ProcessException(
            $message,
            [$this],
        );
        // @codeCoverageIgnoreEnd
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

    private function assertHasTerminated(): void
    {
        if ($this->State !== self::TERMINATED) {
            throw new LogicException('Process has not terminated');
        }
    }
}
