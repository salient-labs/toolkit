<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Console\Target\MockTarget;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Core\MessageLevelGroup as LevelGroup;
use Salient\Core\Facade\Console;
use Salient\Core\Utility\Str;
use Salient\Core\AbstractMultipleErrorException;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\AbstractMultipleErrorException
 * @covers \Salient\Core\Concern\MultipleErrorExceptionTrait
 */
final class AbstractMultipleErrorExceptionTest extends TestCase
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
        $exception = new MyAbstractMultipleErrorException('ohno:');
        $this->assertSame('ohno', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getExitStatus());
        $this->assertSame('ohno', $exception->getMessageWithoutErrors());
        $this->assertSame([], $exception->getErrors());
        $this->assertFalse($exception->hasUnreportedErrors());
        $this->assertStringEndsWith(Str::eolFromNative(<<<'EOF'

            Errors:
            <none>
            EOF), (string) $exception);

        $exception = new MyAbstractMultipleErrorException('ohno:', 'error');
        $this->assertSame('ohno: error', $exception->getMessage());
        $this->assertSame('ohno', $exception->getMessageWithoutErrors());
        $this->assertSame(['error'], $exception->getErrors());
        $this->assertTrue($exception->hasUnreportedErrors());
        $this->assertStringEndsWith(Str::eolFromNative(<<<'EOF'

            Errors:
            - error
            EOF), (string) $exception);

        $exception = new MyAbstractMultipleErrorException('ohno', 'error1', "error2\nerror2, line2");
        $this->assertSame("ohno:\n- error1\n- error2\n  error2, line2", $exception->getMessage());
        $this->assertSame('ohno', $exception->getMessageWithoutErrors());
        $this->assertSame(['error1', "error2\nerror2, line2"], $exception->getErrors());
        $this->assertTrue($exception->hasUnreportedErrors());
        $exception->reportErrors();
        $this->assertFalse($exception->hasUnreportedErrors());
        $this->assertStringEndsWith(Str::eolFromNative(<<<'EOF'

            Errors:
            - error1
            - error2
              error2, line2
            EOF), (string) $exception);
        $this->assertSameConsoleMessages([
            [Level::ERROR, 'Error: error1'],
            [Level::ERROR, "Error:\n  error2\n  error2, line2"],
        ], $this->ConsoleTarget->getMessages());
    }
}

class MyAbstractMultipleErrorException extends AbstractMultipleErrorException {}
