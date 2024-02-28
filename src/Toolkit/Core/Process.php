<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Catalog\Core\FileDescriptor;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\ProcessException;
use Salient\Core\Exception\ProcessTimedOutException;
use Salient\Core\Facade\Profile;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Sys;
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

    private const OPTIONS = [
        'suppress_errors' => true,
        'bypass_shell' => true,
    ];

    /**
     * @var string[]
     */
    private array $Command;

    /**
     * @var resource|string|null
     */
    private $Input;

    private ?string $Cwd;

    /**
     * @var array<string,string>|null
     */
    private ?array $Env;

    private int $Timeout;

    private bool $UseOutputFiles;

    private ?int $Sec = null;

    private int $Usec = 0;

    private int $State = self::READY;

    private ?string $OutputDir = null;

    /**
     * @var int|float
     */
    private $StartTime;

    /**
     * @var resource
     */
    private $Process;

    /**
     * @var array<FileDescriptor::*,resource>
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
     * @var array<FileDescriptor::OUT|FileDescriptor::ERR,string>
     */
    private $Output = [
        FileDescriptor::OUT => '',
        FileDescriptor::ERR => '',
    ];

    /**
     * Creates a new ProcessWrapper object
     *
     * @param string[] $args
     * @param resource|string|null $input
     * @param array<string,string>|null $env
     */
    public function __construct(
        string $command,
        array $args = [],
        $input = '',
        ?string $cwd = null,
        ?array $env = null,
        ?int $timeout = null,
        bool $useOutputFiles = false
    ) {
        $timeout = (int) $timeout;
        if ($timeout < 0) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException(sprintf(
                'Invalid timeout: %d',
                $timeout,
            ));
            // @codeCoverageIgnoreEnd
        }

        $this->Command = [$command, ...$args];
        $this->Input = $input;
        $this->Cwd = $cwd;
        $this->Env = $env;
        $this->Timeout = $timeout;
        $this->UseOutputFiles = $useOutputFiles;

        if ($this->Timeout) {
            $this->Sec = 0;
            $this->Usec = self::TIMEOUT_PRECISION;
        }
    }

    public function __destruct()
    {
        if ($this->isRunning() && $this->stop()->isRunning()) {
            // @codeCoverageIgnoreStart
            $this->close();
            // @codeCoverageIgnoreEnd
        }

        if ($this->OutputDir !== null && is_dir($this->OutputDir)) {
            File::deleteDir($this->OutputDir, true);
        }
    }

    /**
     * Runs the process and returns its exit status
     */
    public function run(): int
    {
        $this->assertHasNotRun();

        if ($this->Input === null) {
            // @codeCoverageIgnoreStart
            $descriptors = [];
            // @codeCoverageIgnoreEnd
        } elseif ($this->Input === '') {
            $descriptors[FileDescriptor::IN] = ['pipe', 'r'];
        } else {
            if (is_string($this->Input)) {
                $this->Input = Str::toStream($this->Input);
            }
            $descriptors[FileDescriptor::IN] = $this->Input;
        }

        $handles = [];

        if ($this->UseOutputFiles || Sys::isWindows()) {
            // Use files in a temporary directory as output buffers because
            // proc_open() blocks until the command exits if standard output
            // pipes are used on Windows
            foreach ([FileDescriptor::OUT, FileDescriptor::ERR] as $fd) {
                $file = ($this->OutputDir ??= File::createTempDir()) . '/' . $fd;
                File::create($file);
                $handles[$fd] = File::open($file, 'r');
                $descriptors[$fd] = ['file', $file, 'w'];
            }
        } else {
            $descriptors += [
                FileDescriptor::OUT => ['pipe', 'w'],
                FileDescriptor::ERR => ['pipe', 'w'],
            ];
        }

        $this->StartTime = hrtime(true);

        $process = $this->throwOnFailure(@proc_open(
            $this->Command,
            $descriptors,
            $pipes,
            $this->Cwd,
            $this->Env,
            self::OPTIONS,
        ));

        if (isset($pipes[FileDescriptor::IN])) {
            File::close($pipes[FileDescriptor::IN]);
            unset($pipes[FileDescriptor::IN]);
        }

        $pipes += $handles;

        foreach ($pipes as $pipe) {
            @stream_set_blocking($pipe, false);
        }

        $this->Process = $process;
        $this->Pipes = $pipes;
        $this->State = self::RUNNING;

        $this->updateStatus();
        $this->Pid = $this->ProcessStatus['pid'];

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
     * True if the process is running
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
     * True if the process ran and terminated
     */
    public function isTerminated(): bool
    {
        return
            $this->State === self::TERMINATED ||
            $this->updateStatus()->State === self::TERMINATED;
    }

    /**
     * Get the command parameters passed to proc_open() to spawn the process
     *
     * @return string[]
     */
    public function getCommand(): array
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
    public function getOutput(int $fd = FileDescriptor::OUT): string
    {
        $this->assertHasRun();

        return $this->Output[$fd];
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
     * @return $this
     */
    private function updateStatus(bool $wait = false)
    {
        if ($this->State !== self::RUNNING) {
            return $this;
        }

        $this->ProcessStatus = $this->throwOnFailure(
            @proc_get_status($this->Process),
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

        if ($this->OutputDir !== null) {
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
            /** @var FileDescriptor::* */
            $i = Arr::keyOf($pipe, $this->Pipes);

            $data = $this->throwOnFailure(
                @stream_get_contents($pipe),
                'Error reading process output: %s',
            );

            if ($data !== '') {
                $this->Output[$i] .= $data;
            }

            if (($this->OutputDir === null || $close) && feof($pipe)) {
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

        $this->throwOnFailure(
            @proc_terminate($this->Process),
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
            $this->closePipes();
            // @codeCoverageIgnoreEnd
        }

        if (is_resource($this->Process)) {
            $result = @proc_close($this->Process);
            if ($result === -1 && is_resource($this->Process)) {
                // @codeCoverageIgnoreStart
                $this->throwOnFailure($result, 'Error closing process: %s', -1);
                // @codeCoverageIgnoreEnd
            }
        }
        unset($this->Process);

        $this->ExitStatus = $this->ProcessStatus['exitcode'];
        $this->State = self::TERMINATED;

        return $this;
    }

    /**
     * @return $this
     *
     * @codeCoverageIgnore
     */
    private function closePipes()
    {
        foreach ($this->Pipes as $pipe) {
            if (is_resource($pipe)) {
                File::close($pipe);
            }
        }
        $this->Pipes = [];

        return $this;
    }

    private function assertHasNotRun(): void
    {
        if ($this->State !== self::READY) {
            throw new ProcessException('Process has already run');
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
     * @template TSuccess
     * @template TFailure of false|-1
     *
     * @param TSuccess|TFailure $result
     * @param TFailure $failure
     * @return ($result is TFailure ? never : TSuccess)
     */
    private function throwOnFailure($result, string $message = 'Error running process: %s', $failure = false)
    {
        if ($result !== $failure) {
            return $result;
        }

        $error = error_get_last();
        if ($error) {
            throw new ProcessException($error['message']);
        }

        // @codeCoverageIgnoreStart
        throw new ProcessException(
            sprintf($message, Get::code($this->Command))
        );
        // @codeCoverageIgnoreEnd
    }
}
