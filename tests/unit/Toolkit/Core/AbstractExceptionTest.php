<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Core\AbstractException;
use Salient\Tests\TestCase;
use Salient\Utility\Str;

/**
 * @covers \Salient\Core\AbstractException
 * @covers \Salient\Core\Concern\ExceptionTrait
 */
final class AbstractExceptionTest extends TestCase
{
    public function testConstructor(): void
    {
        $exception = new MyAbstractException('Foo', null, 8);
        $this->assertSame('Foo', $exception->getMessage());
        $this->assertNull($exception->getPrevious());
        $this->assertSame(8, $exception->getExitStatus());
        $this->assertStringEndsWith(Str::eolFromNative(<<<'EOF'

foo:
bar

baz:
1
EOF), (string) $exception);

        $exception2 = MyAbstractException::withExitStatus(4, 'Bar', $exception);
        $this->assertSame(4, $exception2->getExitStatus());
        $this->assertSame('Bar', $exception2->getMessage());
        $this->assertSame($exception, $exception2->getPrevious());
    }
}

class MyAbstractException extends AbstractException
{
    public function getMetadata(): array
    {
        return ['foo' => 'bar', 'baz' => 1];
    }
}
