<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\Exception\ProcessException;
use Salient\Core\Exception\ProcessTerminatedBySignalException;
use Salient\Core\Exception\ProcessTimedOutException;
use Salient\Core\Process;
use Salient\Tests\TestCase;
use Salient\Utility\File;
use Salient\Utility\Sys;
use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * @covers \Salient\Core\Process
 */
final class ProcessTest extends TestCase
{
    private const ENV_IGNORE = [
        '__CFBundleIdentifier' => true,
        '__CF_USER_TEXT_ENCODING' => true,
        'PROCESSOR_ARCHITECTURE' => true,
        'XPC_FLAGS' => true,
        'XPC_SERVICE_NAME' => true,
    ];

    public function testWithShellCommand(): void
    {
        $process = Process::withShellCommand('echo foo');
        $this->assertSame(0, $process->run());
        $this->assertSame('foo' . \PHP_EOL, $process->getOutput());
        $this->assertSame('', $process->getNewOutput());
        $this->assertSame('', $process->getNewOutputAsText());
        $this->assertSame('foo', $process->getOutputAsText());
    }

    public function testDestructor(): void
    {
        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php', 'timeout'], '', null, null, null, null, true, true);
        $process->start();
        $pid = $process->getPid();
        $this->assertTrue(Sys::isProcessRunning($pid));
        unset($process);
        $this->assertFalse(Sys::isProcessRunning($pid));
    }

    public function testPipeInput(): void
    {
        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php']);
        $pipe = File::openPipe(Sys::escapeCommand([...self::PHP_COMMAND, '-r', "echo 'foo';"]), 'r');
        $this->assertSame(0, $process->pipeInput($pipe)->run());
        $this->assertSame('foo', $process->getOutput());
        $this->assertSame(0, $process->run());
        $this->assertSame('', $process->getOutput());
    }

    public function testWithoutCollectingOutput(): void
    {
        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php'], 'foo', $this->getCallback($output), null, null, null, false);
        $this->assertSame(0, $process->run());
        $this->assertSame('foo', $output[Process::OUT]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Output collection disabled');
        $process->getOutput();
    }

    public function testEnableOutputCollection(): void
    {
        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php'], 'foo', $this->getCallback($output));
        $process->disableOutputCollection();
        $this->assertSame(0, $process->run());
        $this->assertSame('foo', $output[Process::OUT]);
        $process->enableOutputCollection();
        $process->clearOutput();
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Output collection disabled');
        // Should fail because output was not collected when the process was run
        $process->getOutput();
    }

    public function testRunWithoutFail(): void
    {
        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php'], 'foo');
        $this->assertTrue($process->runWithoutFail()->isTerminated());
        $this->assertSame('foo', $process->getOutput());

        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php', 'fail']);
        $this->expectException(ProcessException::class);
        $this->expectExceptionMessage('Process failed with exit status 1: ');
        $process->runWithoutFail();
    }

    public function testWaitForStoppedProcess(): void
    {
        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php', 'timeout']);
        $process->start();
        $pid = $process->getPid();
        $this->assertTrue(Sys::isProcessRunning($pid));
        $process->stop();
        $this->assertFalse(Sys::isProcessRunning($pid));
        $this->assertNotSame(0, $process->getExitStatus());
    }

    public function testWaitForKilledProcess(): void
    {
        if (Sys::isWindows()) {
            $this->markTestSkipped();
        }

        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php', 'timeout']);
        $process->start();
        $pid = $process->getPid();
        $this->assertTrue(Sys::isProcessRunning($pid));
        posix_kill($pid, \SIGKILL);
        $this->expectException(ProcessTerminatedBySignalException::class);
        $this->expectExceptionMessage('Process terminated by signal ' . \SIGKILL);
        $process->wait();
    }

    public function testStart(): void
    {
        foreach (self::runProvider() as $key => $run) {
            [$exitStatus, $stdout, $stderr] = $run;
            $command = [...self::PHP_COMMAND, ...$run[3]];
            $input = $run[4] ?? '';
            $cwd = $run[5] ?? null;
            $env = $run[6] ?? null;
            $timeout = $run[7] ?? null;
            $runs[$key] = [$exitStatus, $stdout, $stderr];

            $process = new Process($command, $input, null, $cwd, $env, $timeout);
            $process->start();
            $processes[$key] = $process;
        }

        $pending = $processes;
        do {
            foreach ($pending as $key => $process) {
                try {
                    $process->poll();
                } catch (ProcessException $ex) {
                    $this->assertSame($runs[$key][0], get_class($ex), $key);
                    unset($pending[$key]);
                    unset($processes[$key]);
                    continue;
                }
                if ($process->isRunning()) {
                    continue;
                }
                unset($pending[$key]);
            }
        } while ($pending);

        foreach ($processes as $key => $process) {
            [$exitStatus, $stdout, $stderr] = $runs[$key];
            $this->assertSame($exitStatus, $process->getExitStatus(), $key);
            $this->assertSame($stdout, $process->getOutput(Process::OUT), $key);
            $this->assertSame($stderr, $process->getOutput(Process::ERR), $key);
        }
    }

    /**
     * @dataProvider runProvider
     *
     * @param int|string $exitStatus
     * @param string[] $command
     * @param resource|string|null $input
     * @param array<string,string>|null $env
     */
    public function testRun(
        $exitStatus,
        string $stdout,
        string $stderr,
        array $command,
        $input = '',
        ?string $cwd = null,
        ?array $env = null,
        ?float $timeout = null
    ): void {
        $this->maybeExpectException($exitStatus);

        $command = [...self::PHP_COMMAND, ...$command];
        $process = new Process($command, $input, null, $cwd, $env, $timeout);
        $this->assertFalse($process->isTerminated());
        $this->assertSame($command, $process->getCommand());
        $result = $process->run();

        $this->assertSame($exitStatus, $result);
        $this->assertSame($exitStatus, $process->getExitStatus());
        $this->assertSame($stdout, $process->getOutput(Process::OUT));
        $this->assertSame($stderr, $process->getOutput(Process::ERR));
        $this->assertTrue($process->isTerminated());
        $this->assertIsInt($pid = $process->getPid());
        $this->assertGreaterThan(0, $pid);
    }

    /**
     * @return non-empty-array<string,array{int|string,string,string,string[],4?:resource|string|null,5?:string|null,6?:array<string,string>|null,7?:float|null}>
     */
    public static function runProvider(): array
    {
        $cat = self::getFixturesPath(__CLASS__) . '/cat.php';

        $env = '';
        self::forEachEnv(
            function (string $key, string $value) use (&$env): void {
                $env .= sprintf('%s=%s' . \PHP_EOL, $key, $value);
            }
        );

        return [
            'empty' => [
                0,
                '',
                '',
                [$cat],
            ],
            'args' => [
                0,
                '',
                <<<'EOF'
- 1: foo
- 2: bar

EOF,
                [$cat, 'foo', 'bar'],
            ],
            'args + input (string with no line break)' => [
                0,
                <<<'EOF'
Foo bar.
EOF,
                <<<'EOF'
- 1: foo
- 2: bar

EOF,
                [$cat, 'foo', 'bar'],
                <<<'EOF'
Foo bar.
EOF,
            ],
            'args + input (multi-line string)' => [
                0,
                <<<'EOF'
Foo.
Bar.
Qux.


EOF,
                <<<'EOF'
- 1: foo
- 2: bar

EOF,
                [$cat, 'foo', 'bar'],
                <<<'EOF'
Foo.
Bar.
Qux.


EOF,
            ],
            'print-env' => [
                0,
                $env,
                '',
                [$cat, 'print-env'],
            ],
            'print-env + env' => [
                0,
                sprintf('TEST=%s' . \PHP_EOL, __CLASS__),
                '',
                [$cat, 'print-env'],
                '',
                null,
                ['TEST' => __CLASS__],
            ],
            'delay after EOF' => [
                2,
                '',
                <<<'EOF'
- 1: delay

EOF,
                [$cat, 'delay'],
            ],
            'time out' => [
                ProcessTimedOutException::class,
                '',
                <<<'EOF'
- 1: timeout

EOF,
                [$cat, 'timeout'],
                '',
                null,
                null,
                0.1,
            ],
        ];
    }

    /**
     * @dataProvider invalidCommandsProvider
     */
    public function testInvalidCommands(string $command): void
    {
        // PHP 8.3 uses posix_spawn for proc_open
        if (\PHP_VERSION_ID >= 80300 || Sys::isWindows()) {
            $this->expectException(ProcessException::class);
        }
        $process = new Process([$command]);
        $result = $process->run();
        $this->assertNotSame(0, $exitStatus = $process->getExitStatus());
        $this->assertSame($exitStatus, $result);
        $this->assertSame('', $process->getOutput(Process::OUT));
        $this->assertSame('', $process->getOutput(Process::ERR));
    }

    /**
     * @return array<array{string}>
     */
    public static function invalidCommandsProvider(): array
    {
        $dir = self::getFixturesPath(__CLASS__);

        return [
            ["$dir/does_not_exist"],
            ["$dir/not_executable"],
        ];
    }

    /**
     * @dataProvider invalidTimeoutProvider
     */
    public function testInvalidTimeout(?float $timeout): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timeout: ');
        new Process([], '', null, null, null, $timeout);
    }

    /**
     * @return array<array{float|null}>
     */
    public static function invalidTimeoutProvider(): array
    {
        return [[-1], [0], [0.0]];
    }

    public function testMultipleRuns(): void
    {
        $process = new Process([...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php'], 'foo');
        $this->assertSame(0, $process->run());
        $this->assertSame('foo', $process->getOutput());
        $this->assertSame(0, $process->setInput('bar')->run());
        $this->assertSame('bar', $process->getOutput());
        $this->assertSame(0, $process->setInput(null)->run());
        $this->assertSame('', $process->getOutput());
    }

    public function testCallback(): void
    {
        $command = [...self::PHP_COMMAND, self::getFixturesPath(__CLASS__) . '/cat.php'];
        $input = File::getContents(__FILE__);

        $this->assertSame(0, $this->doTestCallback(
            fn(Closure $callback): Process =>
                new Process($command, $input, $callback),
            $stdout,
            $stderr,
            $writes,
        ));
        $this->assertSame($input, $stdout);
        $this->assertSame('', $stderr);
        $this->assertSame([1 => 1, 2 => 0], $writes);

        /** @var Process|null */
        $process = null;
        $this->assertSame(0, $this->doTestCallback(
            function (Closure $callback) use ($command, $input, &$process): Process {
                return $process = (new Process([...$command, 'foo', 'bar'], $input))
                    ->setCallback($callback);
            },
            $stdout,
            $stderr,
            $writes,
        ));
        $this->assertSame($input, $stdout);
        $this->assertSame(
            <<<'EOF'
- 1: foo
- 2: bar

EOF,
            $stderr,
        );
        $this->assertSame([1 => 1, 2 => 1], $writes);

        /** @var Process $process */
        $this->assertSame(0, $this->doTestCallback(
            fn(): Process =>
                $process->setCallback(null),
            $stdout,
            $stderr,
            $writes,
        ));
        $this->assertSame('', $stdout);
        $this->assertSame('', $stderr);
        $this->assertSame([1 => 0, 2 => 0], $writes);
    }

    /**
     * @param Closure(Closure(Process::OUT|Process::ERR, string): void): Process $getProcess
     * @param array{1:int,2:int}|null $writes
     * @param-out string $stdout
     * @param-out string $stderr
     * @param-out array{1:int,2:int} $writes
     */
    private function doTestCallback(
        Closure $getProcess,
        ?string &$stdout,
        ?string &$stderr,
        ?array &$writes
    ): int {
        $stdout = '';
        $stderr = '';
        $writes = [1 => 0, 2 => 0];

        return $getProcess(
            static function (int $fd, string $output) use (&$stdout, &$stderr, &$writes): void {
                $writes[$fd]++;
                if ($fd === Process::ERR) {
                    $stderr .= $output;
                    return;
                }
                $stdout .= $output;
            }
        )->run();
    }

    public function testGetExitStatusBeforeRun(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Process has not terminated');
        $process = new Process([]);
        $process->getExitStatus();
    }

    public function testGetPidBeforeRun(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Process has not run');
        $process = new Process([]);
        $process->getPid();
    }

    /**
     * @param callable(string, string): mixed $callback
     */
    public static function forEachEnv(callable $callback): void
    {
        $env = array_diff_key(getenv(), self::ENV_IGNORE);
        ksort($env);

        foreach ($env as $key => $value) {
            $callback($key, $value);
        }
    }

    /**
     * @param array<Process::OUT|Process::ERR,string>|null $output
     * @return Closure(Process::OUT|Process::ERR $fd, string $output): mixed
     */
    private function getCallback(?array &$output): Closure
    {
        $output = [
            Process::OUT => '',
            Process::ERR => '',
        ];

        return static function (int $fd, string $data) use (&$output): void {
            $output[$fd] .= $data;
        };
    }
}
