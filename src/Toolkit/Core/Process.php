<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\FileDescriptor;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\ProcessException;
use Salient\Core\Exception\ProcessTimedOutException;
use Salient\Core\Facade\Profile;
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
     * Microseconds to wait for stream activity
     *
     * This is an upper limit; {@see stream_select()} returns as soon as a
     * status change is detected.
     */
    private const TIMEOUT_PRECISION = 200000;

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

    /**
     * @var (Closure(FileDescriptor::OUT|FileDescriptor::ERR, string): mixed)|null
     */
    private ?Closure $Callback;

    private ?string $Cwd;

    /**
     * @var array<string,string>|null
     */
    private ?array $Env;

    private int $Timeout;

    private bool $UseOutputFiles;

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
    private array $OutputFileStreams;

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
    private $LastReadTime = null;

    /**
     * @var array<FileDescriptor::OUT|FileDescriptor::ERR,resource>
     */
    private $Output = [];

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
        ?int $timeout = null,
        bool $useOutputFiles = false
    ) {
        $timeout = (int) $timeout;
        if ($timeout < 0) {
            throw new InvalidArgumentException(
                sprintf('Invalid timeout: %d', $timeout)
            );
        }

        $this->Command = $command;
        $this->Input = $this->filterInput($input);
        $this->Callback = $callback;
        $this->Cwd = $cwd;
        $this->Env = $env;
        $this->Timeout = $timeout;
        $this->UseOutputFiles = $useOutputFiles || Sys::isWindows();

        if ($this->Timeout) {
            $this->Sec = 0;
            $this->Usec = self::TIMEOUT_PRECISION;
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
        ?int $timeout = null,
        bool $useOutputFiles = false
    ): self {
        $process = new self([], $input, $callback, $cwd, $env, $timeout, $useOutputFiles);
        $process->Command = $command;
        $process->Options = array_diff_key(self::DEFAULT_OPTIONS, ['bypass_shell' => null]);
        return $process;
    }

    public function __destruct()
    {
        if ($this->isRunning()) {
            $this->stop()->assertHasTerminated();
        }

        if (!$this->UseOutputFiles) {
            return;
        }

        if ($this->OutputFileStreams ?? null) {
            $this->closeStreams($this->OutputFileStreams);
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
            File::rewind($this->Input);
            $descriptors[FileDescriptor::IN] = $this->Input;
        }

        if ($this->UseOutputFiles) {
            // Use files in a temporary directory to collect output. This is
            // necessary on Windows, where proc_open() blocks until the process
            // exits if standard output pipes are used. Polling for output by
            // calling $this->isRunning() or $this->getOutput() is optional, so
            // it is also suitable for processes where output monitoring is not
            // required.
            $this->OutputDir ??= File::createTempDir();
            foreach ([FileDescriptor::OUT, FileDescriptor::ERR] as $fd) {
                $file = $this->OutputDir . '/' . $fd;
                $descriptors[$fd] = ['file', $file, 'w'];

                // Use streams in $this->OutputFileStreams to:
                //
                // - create output files before the first run
                // - truncate output files before subsequent runs
                // - service $this->getOutput() during and after each run
                //   (instead of writing the same output to php://temp streams)
                if ($stream = $this->OutputFileStreams[$fd] ?? null) {
                    File::truncate($stream, 0, $file);
                } else {
                    $stream = File::open($file, 'w+');
                    $this->OutputFileStreams[$fd] = $stream;
                }
                $this->Output[$fd] = $stream;

                // Create additional streams to tail output files for this run
                $handles[$fd] = File::open($file, 'r');
            }
        } else {
            $descriptors += [
                FileDescriptor::OUT => ['pipe', 'w'],
                FileDescriptor::ERR => ['pipe', 'w'],
            ];
            $this->Output = [
                FileDescriptor::OUT => File::open('php://temp', 'a+'),
                FileDescriptor::ERR => File::open('php://temp', 'a+'),
            ];
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
     * Wait for the process to exit and return its exit status
     */
    public function wait(): int
    {
        $this->assertHasRun();

        while ($this->Pipes) {
            Profile::count('readIterations', __CLASS__);
            $this->checkTimeout();
            $this->read();
            $this->updateStatus();
        }

        while ($this->isRunning()) {
            Profile::count('waitIterations', __CLASS__);
            $this->checkTimeout();
            usleep(10000);
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
            $this->State === self::RUNNING &&
            $this->updateStatus()->State === self::RUNNING;
    }

    /**
     * Check if the process ran and terminated
     */
    public function isTerminated(): bool
    {
        return
            $this->State === self::TERMINATED ||
            $this->updateStatus()->State === self::TERMINATED;
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
     * Get output written to STDOUT or STDERR by the process
     *
     * @param FileDescriptor::OUT|FileDescriptor::ERR $fd
     * @throws ProcessException if the process has not run.
     */
    public function getOutput(int $fd = FileDescriptor::OUT, bool $trimNativeEol = true): string
    {
        $this->assertHasRun();

        $stream = $this->updateStatus()->Output[$fd];
        $output = File::getContents($stream, 0);
        return $trimNativeEol
            ? Str::trimNativeEol($output)
            : $output;
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

    private function reset(): void
    {
        $this->OutputCallback = null;
        $this->StartTime = null;
        $this->Process = null;
        unset($this->Pipes);
        unset($this->ProcessStatus);
        unset($this->Pid);
        unset($this->ExitStatus);
        $this->LastReadTime = null;
        $this->Output = [];
    }

    /**
     * @return $this
     */
    private function updateStatus(bool $wait = false)
    {
        if ($this->State !== self::RUNNING) {
            return $this;
        }

        /** @var resource */
        $process = $this->Process;
        $this->ProcessStatus = $this->throwOnFailure(
            @proc_get_status($process),
            'Error getting process status: %s',
        );

        $running = $this->ProcessStatus['running'];
        $this->read($running && $wait, !$running);

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

        $read = $this->Pipes;

        if ($this->UseOutputFiles) {
            if ($this->LastReadTime === null || !$wait) {
                $this->LastReadTime = hrtime(true);
            } else {
                $now = hrtime(true);
                $usec = (int) (self::TIMEOUT_PRECISION - ($now - $this->LastReadTime) / 1000);
                $this->LastReadTime = $now;
                if ($usec > 0) {
                    usleep($usec);
                }
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

            Profile::count(sprintf('readOperations#%d', $i), __CLASS__);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws ProcessTimedOutException if the process timed out.
     */
    private function checkTimeout()
    {
        if (
            $this->State !== self::RUNNING ||
            !$this->Timeout ||
            $this->Timeout > (hrtime(true) - $this->StartTime) / 1000000000
        ) {
            return $this;
        }

        try {
            $this->stop();
            // @codeCoverageIgnoreStart
        } catch (Throwable $ex) {
            throw new ProcessException(sprintf(
                'Error terminating process that timed out after %ds: %s',
                $this->Timeout,
                Get::code($this->Command),
            ), $ex);
            // @codeCoverageIgnoreEnd
        }

        throw new ProcessTimedOutException(sprintf(
            'Process timed out after %ds: %s',
            $this->Timeout,
            Get::code($this->Command),
        ));
    }

    /**
     * @todo Implement timeout, use signals
     *
     * @return $this
     */
    private function stop()
    {
        if (!$this->isRunning()) {
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

        usleep(10000);

        return $this;
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
