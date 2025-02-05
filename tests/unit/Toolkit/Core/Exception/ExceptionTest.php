<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\Exception\Exception;
use Salient\Tests\TestCase;
use Salient\Utility\Str;

/**
 * @covers \Salient\Core\Exception\Exception
 * @covers \Salient\Core\Exception\ExceptionTrait
 */
final class ExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new MyException('Foo', null, 8);
        $this->assertSame('Foo', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertSame(8, $exception->getExitStatus());
        $this->assertStringEndsWith(Str::eolFromNative(<<<'EOF'

foo:
bar

baz:
1
EOF), (string) $exception);

        $exception2 = new MyException('Bar', $exception, 4);
        $this->assertSame(4, $exception2->getExitStatus());
        $this->assertSame('Bar', $exception2->getMessage());
        $this->assertSame($exception, $exception2->getPrevious());
    }
}

class MyException extends Exception
{
    public function getMetadata(): array
    {
        return ['foo' => 'bar', 'baz' => 1];
    }
}
