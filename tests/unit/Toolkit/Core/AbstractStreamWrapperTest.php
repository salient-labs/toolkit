<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Tests\MockStream;
use Salient\Tests\TestCase;

/**
 * @covers \Salient\Core\AbstractStreamWrapper
 */
final class AbstractStreamWrapperTest extends TestCase
{
    public function testStreamWrapper(): void
    {
        $this->assertTrue(stream_wrapper_register('mock', MockStream::class));
        try {
            $this->assertNotFalse(file_put_contents('mock://input', 'Foo'));
            $this->assertSame('Foo', file_get_contents('mock://input'));
        } finally {
            stream_wrapper_unregister('mock');
        }
    }
}
