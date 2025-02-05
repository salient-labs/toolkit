<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Contract\Catalog\MessageLevel as Level;
use Salient\Contract\Catalog\MessageLevelGroup as LevelGroup;
use Salient\Core\Exception\MultipleErrorException;
use Salient\Core\Facade\Console;
use Salient\Testing\Console\MockTarget;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\Exception\MultipleErrorException
 * @covers \Salient\Core\Exception\MultipleErrorExceptionTrait
 */
final class MultipleErrorExceptionTest extends TestCase
{
    private MockTarget $ConsoleTarget;

    protected function setUp(): void
    {
        $this->ConsoleTarget = new MockTarget();
        Console::registerTarget($this->ConsoleTarget, LevelGroup::ALL_EXCEPT_DEBUG);
    }

    protected function tearDown(): void
    {
        Console::unload();
    }

    public function testConstructor(): void
    {
        $exception = new MyMultipleErrorException('ohno:');
        $this->assertSame('ohno', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getExitStatus());
        $this->assertSame('ohno', $exception->getMessageOnly());
        $this->assertSame([], $exception->getErrors());
        $this->assertFalse($exception->hasUnreportedErrors());

        $exception = new MyMultipleErrorException('ohno:', 'error');
        $this->assertSame('ohno: error', $exception->getMessage());
        $this->assertSame('ohno', $exception->getMessageOnly());
        $this->assertSame(['error'], $exception->getErrors());
        $this->assertTrue($exception->hasUnreportedErrors());

        $exception = new MyMultipleErrorException('ohno', 'error1', "error2\nerror2, line2");
        $this->assertSame("ohno:\n- error1\n- error2\n  error2, line2", $exception->getMessage());
        $this->assertSame('ohno', $exception->getMessageOnly());
        $this->assertSame(['error1', "error2\nerror2, line2"], $exception->getErrors());
        $this->assertTrue($exception->hasUnreportedErrors());
        $exception->reportErrors(Console::getInstance());
        $this->assertFalse($exception->hasUnreportedErrors());
        $this->assertSameConsoleMessages([
            [Level::ERROR, 'Error: error1'],
            [Level::ERROR, "Error:\n  error2\n  error2, line2"],
        ], $this->ConsoleTarget->getMessages());
    }
}

class MyMultipleErrorException extends MultipleErrorException {}
