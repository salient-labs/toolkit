<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\FileDescriptor;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\ProcessException;
use Salient\Core\Exception\ProcessTimedOutException;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Sys;
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
     * @var string[]|string
     */
    private $Command;

    /**
     * @var resource|null
     */
    private $Input;

    private bool $RewindInput;

    /**
     * @var (Closure(FileDescriptor::OUT|FileDescriptor::ERR, string): mixed)|null
     */
    private ?Closure $Callback;

    private ?string $Cwd;

    /**
     * @var array<string,string>|null
     */
    private ?array $Env;

    private ?float $Timeout;

    private bool $UseOutputFiles;

    private bool $CollectOutput;

    /**
     * @var array<string,bool>|null
     */
    private ?array $Options = null;

    private ?int $Sec = null;

    private int $Usec = 0;

    private int $State = self::READY;

    /**
     * @var (Closure(FileDescriptor::OUT|FileDescriptor::ERR, string): mixed)|null
     */
    private ?Closure $OutputCallback = null;

    private ?string $OutputDir = null;

    /**
     * @var array<FileDescriptor::OUT|FileDescriptor::ERR,resource>
     */
    private array $OutputFiles;

    /**
     * @var array<FileDescriptor::OUT|FileDescriptor::ERR,int<0,max>>
     */
    private array $OutputFilePos;

    /**
     * @var int|float|null
     */
    private $StartTime = null;

    /**
     * @var resource|null
     */
    private $Process = null;

    /**
     * @var array<FileDescriptor::OUT|FileDescriptor::ERR,resource>
     */
    private array $Pipes;

    /**
     * @var array{command:string,pid:int,running:bool,signaled:bool,stopped:bool,exitcode:int,termsig:int,stopsig:int}
     */
    private array $ProcessStatus;

    private int $Pid;

    private int $ExitStatus;

    /**
     * @var int|float|null
     */
    private $LastPollTime = null;

    /**
     * @var int|float|null
     */
    private $LastReadTime = null;

    /**
     * @var array<FileDescriptor::OUT|FileDescriptor::ERR,resource>
     */
    private array $Output = [];

    /**
     * @var array<FileDescriptor::OUT|FileDescriptor::ERR,int<0,max>>
     */
    private array $OutputPos = [];

    /**
     * @var array<string,mixed>
     */
    private array $Stats = [];

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
        $input = '',
        ?Closure $callback = null,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
        bool $useOutputFiles = false,
        bool $collectOutput = true
    ) {
        if ($timeout !== null && $timeout <= 0) {
            throw new InvalidArgumentException(
                sprintf('Invalid timeout: %.3fs', $timeout)
            );
        }

        $this->Command = $command;
        $this->Input = $this->filterInput($input);
        if ($this->Input !== null) {
            $this->RewindInput = true;
        }
        $this->Callback = $callback;
        $this->Cwd = $cwd;
        $this->Env = $env;
        $this->Timeout = $timeout;
        $this->UseOutputFiles = $useOutputFiles || Sys::isWindows();
        $this->CollectOutput = $collectOutput;

        if ($this->Timeout !== null) {
            $this->Sec = 0;
            $this->Usec = self::READ_INTERVAL;
        }
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
        $input = '',
        ?Closure $callback = null,
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null,
        bool $useOutputFiles = false,
        bool $collectOutput = true
    ): self {
        $process = new self([], $input, $callback, $cwd, $env, $timeout, $useOutputFiles, $collectOutput);
        $process->Command = $command;
        $process->Options = array_diff_key(self::DEFAULT_OPTIONS, ['bypass_shell' => null]);
        return $process;
    }

    public function __destruct()
    {
        if ($this->updateStatus()->isRunning()) {
            $this->stop()->assertHasTerminated();
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
     * Set or unset the input received by the process
     *
     * @param resource|string|null $input
     * @return $this
     * @throws ProcessException if the process is running.
     */
    public function setInput($input)
    {
        $this->assertIsNotRunning();

        $this->Input = $this->filterInput($input);
        if ($this->Input !== null) {
            $this->RewindInput = true;
        } else {
            unset($this->RewindInput);
        }
        return $this;
    }

    /**
     * Pass input to the process without making it seekable or rewinding it
     * between runs
     *
     * @param resource $input
     * @return $this
     * @throws ProcessException if the process is running.
     */
    public function pipeInput($input)
    {
        $this->assertIsNotRunning();

        $this->Input = $input;
        $this->RewindInput = false;
        return $this;
    }

    /**
     * Set or unset the callback that receives output from the process
     *
     * @param (Closure(FileDescriptor::OUT|FileDescriptor::ERR $fd, string $output): mixed)|null $callback
     * @return $this
     * @throws ProcessException if the process is running.
     */
    public function setCallback(?Closure $callback)
    {
        $this->assertIsNotRunning();

        $this->Callback = $callback;
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

        $descriptors = [];
        $handles = [];

        if ($this->Input !== null) {
            if ($this->RewindInput) {
                File::rewind($this->Input);
            }
            $descriptors[FileDescriptor::IN] = $this->Input;
        }

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
        $this->Stats['spawn_us'] = ($now - $this->StartTime) / 1000;

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

        return $this->ExitStatus;
    }

    /**
     * Check if the process is running
     *
     * @phpstan-impure
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
     * @throws ProcessException if the process has not run.
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
     * @throws ProcessException if the process has not run or if output
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
     * @throws ProcessException if the process has not run or if output
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
     * @throws ProcessException if the process has not run or if output
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
     * @throws ProcessException if the process has not run or if output
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

        if (!$this->CollectOutput) {
            throw new ProcessException('Output collection is disabled');
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
        if (!$this->CollectOutput || $this->State === self::READY) {
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
     * @return array<string,mixed>
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
        unset($this->Pipes);
        unset($this->ProcessStatus);
        unset($this->Pid);
        unset($this->ExitStatus);
        $this->LastPollTime = null;
        $this->LastReadTime = null;
        $this->Output = [];
        $this->OutputPos = [];
        $this->Stats = [];
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
        if ($this->State !== self::RUNNING) {
            return $this;
        }

        $now = hrtime(true);

        assert($this->Process !== null);
        $this->ProcessStatus = $this->throwOnFailure(
            @proc_get_status($this->Process),
            'Error getting process status: %s',
        );

        $this->LastPollTime = $now;

        $running = $this->ProcessStatus['running'];

        if ($read || !$running) {
            $this->read($running && $wait, !$running);
        }

        if (!$running) {
            $this->close();
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function read(bool $wait = true, bool $close = false)
    {
        if (!$this->Pipes) {
            return $this;
        }

        $now = hrtime(true);
        $read = $this->Pipes;

        if ($this->UseOutputFiles) {
            if ($wait) {
                $this->awaitInterval($this->LastReadTime, self::READ_INTERVAL);
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

        foreach ($read as $pipe) {
            /** @var FileDescriptor::OUT|FileDescriptor::ERR */
            $i = Arr::keyOf($pipe, $this->Pipes);

            $data = $this->throwOnFailure(
                @stream_get_contents($pipe),
                'Error reading process output: %s',
            );

            if ($data !== '') {
                if (!$this->UseOutputFiles) {
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

        return $this;
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

    /**
     * Terminate the process if it is still running
     *
     * @todo Implement timeout, use signals
     *
     * @return $this
     */
    public function stop()
    {
        $this->assertHasRun();

        if (!$this->updateStatus()->isRunning()) {
            // @codeCoverageIgnoreStart
            return $this;
            // @codeCoverageIgnoreEnd
        }

        /** @var resource */
        $process = $this->Process;
        $this->throwOnFailure(
            @proc_terminate($process),
            'Error terminating process: %s',
        );

        usleep(self::POLL_INTERVAL);

        return $this->updateStatus();
    }

    /**
     * @return $this
     */
    private function close()
    {
        if ($this->State !== self::RUNNING) {
            // @codeCoverageIgnoreStart
            return $this;
            // @codeCoverageIgnoreEnd
        }

        if ($this->Pipes) {
            // @codeCoverageIgnoreStart
            $this->closeStreams($this->Pipes);
            // @codeCoverageIgnoreEnd
        }

        if (is_resource($this->Process)) {
            // The return value of `proc_close()` is not reliable, so ignore it
            // and use `error_get_last()` to check for errors
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

        $this->ExitStatus = $this->ProcessStatus['exitcode'];
        $this->State = self::TERMINATED;

        return $this;
    }

    /**
     * @param array<FileDescriptor::*,resource> $streams
     * @return $this
     */
    private function closeStreams(array &$streams)
    {
        foreach ($streams as $stream) {
            if (is_resource($stream)) {
                File::close($stream);
            }
        }
        $streams = [];

        return $this;
    }

    private function assertIsNotRunning(): void
    {
        if ($this->State === self::RUNNING) {
            throw new ProcessException('Process is running');
        }
    }

    private function assertHasRun(): void
    {
        if ($this->State === self::READY) {
            throw new ProcessException('Process has not run');
        }
    }

    private function assertHasTerminated(): void
    {
        if ($this->State !== self::TERMINATED) {
            throw new ProcessException('Process is not terminated');
        }
    }

    /**
     * @param resource|string|null $input
     * @return resource|null
     */
    private function filterInput($input)
    {
        return $input === null
            // @codeCoverageIgnoreStart
            ? null
            // @codeCoverageIgnoreEnd
            : (is_string($input)
                ? Str::toStream($input)
                : File::getSeekableStream($input));
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
