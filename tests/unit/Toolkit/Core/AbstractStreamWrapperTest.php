<?php declare(strict_types=1);

namespace Salient\Tests\Core;

use Salient\Testing\Core\MockPhpStream;
use Salient\Tests\TestCase;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\File;

/**
 * @covers \Salient\Core\AbstractStreamWrapper
 * @covers \Salient\Testing\Core\MockPhpStream
 */
final class AbstractStreamWrapperTest extends TestCase
{
    public function testStreamWrapper(): void
    {
        MockPhpStream::register('mock');
        try {
            $this->assertSame(6, File::writeContents('mock://input', 'foobar'));
            $this->assertSame(3, File::writeContents('mock://temp', 'baz'));
            $this->assertSame(5, File::writeContents('mock://fd/2', 'Error'));
            $this->assertSame('foobar', File::getContents('mock://input'));
            $this->assertSame('baz', File::getContents('mock://temp/'));
            $this->assertSame('Error', File::getContents('mock://fd/02'));
            MockPhpStream::reset();
            $this->assertSame('', File::getContents('mock://input'));
            $invalid = [
                'mock://input/',
                'mock://fd/foo',
                'mock://filter',
            ];
            foreach ($invalid as $path) {
                $this->assertCallbackThrowsException(
                    fn() => File::writeContents($path, ''),
                    FilesystemErrorException::class,
                );
            }
        } finally {
            MockPhpStream::deregister();
        }
    }
}
