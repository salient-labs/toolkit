<?php declare(strict_types=1);

namespace Salient\Tests\Core\Utility;

use Salient\Core\Utility\File;
use Salient\Core\Utility\Sys;
use Salient\Tests\TestCase;
use LogicException;

/**
 * @covers \Salient\Core\Utility\Sys
 */
final class SysTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testGetMemoryLimit(): void
    {
        ini_set('memory_limit', '-1');
        $this->assertSame(-1, Sys::getMemoryLimit());
        ini_set('memory_limit', '512M');
        $this->assertSame(512 * 2 ** 20, Sys::getMemoryLimit());
    }

    public function testGetMemoryUsage(): void
    {
        $this->assertGreaterThan(0, $current = Sys::getMemoryUsage());
        $this->assertGreaterThanOrEqual($current, Sys::getPeakMemoryUsage());
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetMemoryUsagePercent(): void
    {
        ini_set('memory_limit', '-1');
        $this->assertSame(0.0, Sys::getMemoryUsagePercent());
        ini_set('memory_limit', '512M');
        $this->assertGreaterThan(0.0, Sys::getMemoryUsagePercent());
    }

    public function testGetCpuUsage(): void
    {
        $this->assertCount(2, $usage = Sys::getCpuUsage());
        foreach ($usage as $cpuTime) {
            $this->assertIsInt($cpuTime);
            $this->assertGreaterThan(0, $cpuTime);
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetProgramName(): void
    {
        $dir = self::getPackagePath();
        $file = self::getFixturesPath(__CLASS__) . '/unescape.php';
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $this->assertTrue(File::same($file, Sys::getProgramName()));
        $this->assertTrue(File::same($file, $dir . '/' . Sys::getProgramName($dir)));
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("' is not in '");
        Sys::getProgramName(__DIR__);
    }

    /**
     * @runInSeparateProcess
     */
    public function testGetProgramBasename(): void
    {
        $file = self::getFixturesPath(__CLASS__) . '/unescape.php';
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $this->assertSame('unescape.php', Sys::getProgramBasename());
        $this->assertSame('unescape', Sys::getProgramBasename('.php'));
    }

    public function testGetUserId(): void
    {
        $userId = Sys::getUserId();
        if (Sys::isWindows()) {
            $this->assertIsString($userId);
            $this->assertNotEquals('', $userId);
            return;
        }
        $this->assertIsInt($userId);
        $this->assertNotEquals(0, $userId);
    }

    public function testIsProcessRunning(): void
    {
        $this->assertIsInt($pid = getmypid());
        $this->assertTrue(Sys::isProcessRunning($pid));
        $command = Sys::escapeCommand([
            ...self::PHP_COMMAND,
            '-r',
            'echo getmypid();',
        ]);
        $handle = File::openPipe($command, 'r');
        $output = File::getContents($handle);
        $status = File::closePipe($handle);
        $this->assertSame(0, $status);
        $this->assertFalse(Sys::isProcessRunning((int) $output));
    }

    /**
     * @dataProvider escapeCommandProvider
     */
    public function testEscapeCommand(string $arg): void
    {
        if (Sys::isWindows() && strpos($arg, \PHP_EOL) !== false) {
            $this->markTestSkipped();
        }

        $command = Sys::escapeCommand([
            ...self::PHP_COMMAND,
            self::getFixturesPath(__CLASS__) . '/unescape.php',
            $arg,
        ]);
        $handle = File::openPipe($command, 'rb');
        $output = File::getContents($handle);
        $status = File::closePipe($handle);
        $this->assertSame(0, $status);
        $this->assertSame($arg . \PHP_EOL, $output);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function escapeCommandProvider(): array
    {
        return [
            'empty string' => [''],
            'special characters' => ['!"$%&\'*+,;<=>?[\]^`{|}~'],
            'special characters + whitespace' => [' ! " $ % & \' * + , ; < = > ? [ \ ] ^ ` { | } ~ '],
            'path' => ['/some/path'],
            'path + spaces' => ['/some/path with spaces'],
            'quoted' => ['"string"'],
            'quoted + backslashes' => ['\"string\"'],
            'quoted + whitespace' => ['"string with words"'],
            'quoted + whitespace + backslashes' => ['\"string with words\"'],
            'quoted (single + double)' => ['\'quotable\' "quotes"'],
            'unquoted + special (cmd) #1' => ['this&that'],
            'unquoted + special (cmd) #2' => ['this^that'],
            'unquoted + special (cmd) #3' => ['(this|that)'],
            'cmd variable expansion #1' => ['%path%'],
            'cmd variable expansion #2' => ['!path!'],
            'cmd variable expansion #3' => ['value%'],
            'cmd variable expansion #4' => ['success!'],
            'cmd variable expansion #5' => ['string^%'],
            'cmd variable expansion #6' => ['string^!'],
            'cmd variable expansion #7' => ['^%string^%'],
            'cmd variable expansion #8' => ['^!string^!'],
            'with newline' => ['line' . \PHP_EOL . 'line'],
            'with blank line' => ['line' . \PHP_EOL . \PHP_EOL . 'line'],
            'with trailing newline' => ['line' . \PHP_EOL],
            'with trailing space' => ['string '],
            'with trailing backslash' => ['string\\'],
            'with trailing backslashes' => ['string\\\\'],
        ];
    }

    public function testIsWindows(): void
    {
        $this->assertSame(\DIRECTORY_SEPARATOR === '\\', Sys::isWindows());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleExitSignals(): void
    {
        $this->assertSame(!Sys::isWindows(), Sys::handleExitSignals());
    }
}
