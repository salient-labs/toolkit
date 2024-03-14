<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Contract\Core\FileDescriptor;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\ProcessException;
use Salient\Core\Exception\ProcessTimedOutException;
use Salient\Core\Facade\Profile;
use Salient\Core\Utility\Sys;
use Salient\Core\Process;
use Salient\Tests\TestCase;
use Closure;

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

    /**
     * @dataProvider runProvider
     *
     * @param int|string $exitStatus
     * @param array<array{string,string,string,...}> $counters
     * @param string[] $args
     * @param resource|string|null $input
     * @param (Closure(FileDescriptor::OUT|FileDescriptor::ERR, string): mixed)|null $callback
     * @param array<string,string>|null $env
     */
    public function testRun(
        $exitStatus,
        string $stdout,
        string $stderr,
        array $counters,
        array $args = [],
        $input = '',
        ?Closure $callback = null,
        ?string $cwd = null,
        ?array $env = null,
        ?int $timeout = null,
        bool $useOutputFiles = false
    ): void {
        $this->maybeExpectException($exitStatus);

        $args = ['-ddisplay_startup_errors=0', ...$args];
        $process = new Process([\PHP_BINARY, ...$args], $input, $callback, $cwd, $env, $timeout, $useOutputFiles);
        $this->assertFalse($process->isTerminated());
        $this->assertSame([\PHP_BINARY, ...$args], $process->getCommand());
        $result = $process->run();

        $this->assertSame($exitStatus, $result);
        $this->assertSame($exitStatus, $process->getExitStatus());
        $this->assertSame($stdout, $process->getOutput(FileDescriptor::OUT, false));
        $this->assertSame($stderr, $process->getOutput(FileDescriptor::ERR, false));
        $this->assertTrue($process->isTerminated());
        $this->assertIsInt($pid = $process->getPid());
        $this->assertGreaterThan(0, $pid);

        $skipWaitIterations = $useOutputFiles || Sys::isWindows();
        foreach ($counters as $counterArgs) {
            $assertion = array_shift($counterArgs);
            $counter = array_shift($counterArgs);
            if ($skipWaitIterations && $counter === 'waitIterations') { continue; }
            $group = array_shift($counterArgs);
            $counterArgs[] = Profile::getCounter($counter, $group);
            $this->$assertion(...[...$counterArgs, $counter]);
        }
        if ($skipWaitIterations) {
            $this->assertSame(0, Profile::getCounter('waitIterations', Process::class));
        }
    }

    /**
     * @return array<string,array{int|string,string,string,array<array{string,string,string,...}>,4?:string[],5?:resource|string|null,6?:(Closure(FileDescriptor::OUT|FileDescriptor::ERR, string): mixed)|null,7?:string|null,8?:array<string,string>|null,9?:int|null}>
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

        $counters = [
            ['assertGreaterThan', 'readOperations#1', Process::class, 0],
            ['assertGreaterThan', 'readOperations#2', Process::class, 0],
            ['assertLessThan', 'readOperations#1', Process::class, 50],
            ['assertLessThan', 'readOperations#2', Process::class, 50],
            ['assertLessThan', 'readIterations', Process::class, 50],
        ];

        $delayAfterEofCounters = $counters;
        $delayAfterEofCounters[] = ['assertGreaterThan', 'waitIterations', Process::class, 10];
        $delayAfterEofCounters[] = ['assertLessThan', 'waitIterations', Process::class, 50];

        $counters[] = ['assertLessThan', 'waitIterations', Process::class, 5];

        return [
            'empty' => [
                0,
                '',
                '',
                $counters,
                [$cat],
            ],
            'args' => [
                0,
                '',
                <<<'EOF'
                - 1: foo
                - 2: bar

                EOF,
                $counters,
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
                $counters,
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
                $counters,
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
                $counters,
                [$cat, 'print-env'],
            ],
            'print-env + env' => [
                0,
                sprintf('TEST=%s' . \PHP_EOL, __CLASS__),
                '',
                $counters,
                [$cat, 'print-env'],
                '',
                null,
                null,
                ['TEST' => __CLASS__],
            ],
            'delay after EOF' => [
                0,
                '',
                <<<'EOF'
                - 1: delay

                EOF,
                $delayAfterEofCounters,
                [$cat, 'delay'],
            ],
            'time out' => [
                ProcessTimedOutException::class,
                '',
                <<<'EOF'
                - 1: timeout

                EOF,
                $counters,
                [$cat, 'timeout'],
                '',
                null,
                null,
                null,
                1,
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
        $this->assertSame('', $process->getOutput(FileDescriptor::OUT));
        $this->assertSame('', $process->getOutput(FileDescriptor::ERR));
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

    public function testInvalidTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid timeout: -1');
        new Process([], '', null, null, null, -1);
    }

    public function testRunTwice(): void
    {
        $process = new Process([\PHP_BINARY, self::getFixturesPath(__CLASS__) . '/cat.php'], 'foo');
        $this->assertSame(0, $process->run());
        $this->assertSame('foo', $process->getOutput());
        $this->assertSame(0, $process->setInput('bar')->run());
        $this->assertSame('bar', $process->getOutput());
    }

    public function testGetExitStatusBeforeRun(): void
    {
        $this->expectException(ProcessException::class);
        $this->expectExceptionMessage('Process is not terminated');
        $process = new Process([\PHP_BINARY]);
        $process->getExitStatus();
    }

    public function testGetPidBeforeRun(): void
    {
        $this->expectException(ProcessException::class);
        $this->expectExceptionMessage('Process has not run');
        $process = new Process([\PHP_BINARY]);
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

    protected function setUp(): void
    {
        Profile::push();
    }

    protected function tearDown(): void
    {
        Profile::pop();
    }
}
