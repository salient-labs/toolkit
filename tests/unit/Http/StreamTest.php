<?php declare(strict_types=1);

namespace Lkrms\Tests\Http;

use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Http\Exception\StreamDetachedException;
use Lkrms\Http\Exception\StreamInvalidRequestException;
use Lkrms\Http\Stream;
use Lkrms\Utility\File;
use Lkrms\Utility\Format;

/**
 * Some tests are derived from similar guzzlehttp/psr7 tests
 */
final class StreamTest extends \Lkrms\Tests\TestCase
{
    /**
     * @var resource|null
     */
    private $LastHandle;

    public function testInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Argument #1 ($stream) must be of type resource, string given'
        );
        // @phpstan-ignore-next-line
        new Stream(__METHOD__);
    }

    /**
     * @dataProvider streamProvider
     */
    public function testStream(string $mode): void
    {
        $stream = $this->getStream($mode);
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertSame('php://temp', $stream->getMetadata('uri'));
        self::assertIsArray($stream->getMetadata());
        self::assertSame(4, $stream->getSize());
        self::assertFalse($stream->eof());
        $stream->close();
    }

    /**
     * @return array<array{string}>
     */
    public static function streamProvider(): array
    {
        return [
            ['r+'],
            ['rb+'],
        ];
    }

    public function testToString(): void
    {
        $stream = $this->getStream();
        self::assertSame('data', (string) $stream);
        self::assertSame('data', (string) $stream);
        $stream->close();
    }

    public function testForwardOnlyStream(): void
    {
        $stream = $this->getForwardOnlyStream();
        self::assertFalse($stream->isSeekable());
        self::assertSame('data', trim((string) $stream));
        $stream->close();

        $stream = $this->getForwardOnlyStream();
        $firstLetter = $stream->read(1);
        self::assertFalse($stream->isSeekable());
        self::assertSame('d', $firstLetter);
        self::assertSame('ata', trim((string) $stream));
        $stream->close();
    }

    public function testGetContents(): void
    {
        $stream = $this->getStream();
        self::assertSame('', $stream->getContents());
        $stream->seek(0);
        self::assertSame('data', $stream->getContents());
        self::assertSame('', $stream->getContents());
        $stream->close();
    }

    public function testEof(): void
    {
        $stream = $this->getStream();
        self::assertSame(4, $stream->tell());
        self::assertFalse($stream->eof());
        self::assertSame('', $stream->read(1));
        self::assertTrue($stream->eof());
        $stream->close();
    }

    public function testGetSize(): void
    {
        $size = filesize(__FILE__);
        $handle = fopen(__FILE__, 'r');
        $stream = new Stream($handle);
        self::assertSame($size, $stream->getSize());
        self::assertSame($size, $stream->getSize());
        $stream->close();

        $stream = $this->getStream();
        self::assertSame(4, $stream->getSize());
        self::assertSame(3, $stream->write('123'));
        self::assertSame(7, $stream->getSize());
        self::assertSame(7, $stream->getSize());
        $stream->close();
    }

    public function testTell(): void
    {
        $stream = $this->getStream('r+', null);
        self::assertSame(0, $stream->tell());
        $stream->write('foo');
        self::assertSame(3, $stream->tell());
        $stream->seek(1);
        self::assertSame(1, $stream->tell());
        self::assertSame(ftell($this->LastHandle), $stream->tell());
        $stream->close();
    }

    public function testDetach(): void
    {
        $stream = $this->getStream('r', null);
        self::assertSame($this->LastHandle, $stream->detach());
        self::assertIsResource($this->LastHandle, 'Underlying PHP stream should not be closed');
        self::assertNull($stream->detach());
        $this->assertDetached($stream);
        $stream->close();
        fclose($this->LastHandle);
    }

    public function testClose(): void
    {
        $stream = $this->getStream('r', null);
        self::assertIsResource($this->LastHandle);
        $stream->close();
        self::assertFalse(is_resource($this->LastHandle));
        $this->assertDetached($stream);
    }

    private function assertDetached(Stream $stream): void
    {
        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertNull($stream->getSize());
        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('mode'));

        $throws = function (callable $fn): void {
            try {
                $fn();
            } catch (\Exception $e) {
                $this->assertStringContainsString('Stream is detached', $e->getMessage());

                return;
            }

            $this->fail('Exception should be thrown after the stream is detached.');
        };

        foreach ([
            '__toString' => fn() => (string) $stream,
            'getContents' => fn() => $stream->getContents(),
            'tell' => fn() => $stream->tell(),
            'eof' => fn() => $stream->eof(),
            'rewind' => fn() => $stream->rewind(),
            'read' => fn() => $stream->read(1),
            'write' => fn() => $stream->write('foo'),
            'seek' => fn() => $stream->seek(0),
        ] as $method => $callback) {
            $this->assertThrows(
                $callback,
                StreamDetachedException::class,
                'Stream is detached',
                sprintf('%s::%s() should throw an exception after stream is detached', Stream::class, $method)
            );
        }
    }

    public function testReadInvalidLength(): void
    {
        $stream = $this->getStream('r', null);
        self::assertSame('', $stream->read(0));
        $stream->close();

        $stream = $this->getStream('r', null);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($length) must be greater than or equal to 0');
        try {
            $stream->read(-1);
        } finally {
            $stream->close();
        }
    }

    /**
     * @dataProvider streamModeProvider
     */
    public function testStreamMode(string $mode, bool $readable, bool $writable, bool $temp = false): void
    {
        $file = $temp
            ? File::createTempDir() . '/does_not_exist'
            : self::getFixturesPath(__CLASS__) . '/file';

        try {
            $stream = $this->getStream($mode, null, $file);
            $actualMode = $stream->getMetadata('mode');
            self::assertSame(
                $readable,
                $stream->isReadable(),
                sprintf('isReadable() should be %s for mode %s (actual mode: %s)', Format::bool($readable), $mode, $actualMode)
            );
            self::assertSame(
                $writable,
                $stream->isWritable(),
                sprintf('isWritable() should be %s for mode %s (actual mode: %s)', Format::bool($writable), $mode, $actualMode)
            );
        } finally {
            if (isset($stream)) {
                $stream->close();
            }
            if ($temp) {
                $dir = dirname($file);
                File::pruneDir($dir);
                rmdir($dir);
            }
        }
    }

    /**
     * @return array<array{string,bool,bool,3?:bool}>
     */
    public static function streamModeProvider(): array
    {
        return [
            'a' => ['a', false, true],
            'ab' => ['ab', false, true],
            'at' => ['at', false, true],
            'a+' => ['a+', true, true],
            'a+b' => ['a+b', true, true],
            'a+t' => ['a+t', true, true],
            'c' => ['c', false, true],
            'cb' => ['cb', false, true],
            'ct' => ['ct', false, true],
            'c+' => ['c+', true, true],
            'c+b' => ['c+b', true, true],
            'c+t' => ['c+t', true, true],
            'r' => ['r', true, false],
            'rb' => ['rb', true, false],
            'rt' => ['rt', true, false],
            'r+' => ['r+', true, true],
            'r+b' => ['r+b', true, true],
            'r+t' => ['r+t', true, true],
            'w' => ['w', false, true],
            'wb' => ['wb', false, true],
            'wt' => ['wt', false, true],
            'w+' => ['w+', true, true],
            'w+b' => ['w+b', true, true],
            'w+t' => ['w+t', true, true],
            'x' => ['x', false, true, true],
            'xb' => ['xb', false, true, true],
            'xt' => ['xt', false, true, true],
            'x+' => ['x+', true, true, true],
            'x+b' => ['x+b', true, true, true],
            'x+t' => ['x+t', true, true, true],
            'rw' => ['rw', true, true],
            'rb+' => ['rw', true, true],
        ];
    }

    /**
     * @requires extension zlib
     *
     * @dataProvider gzipStreamModeProvider
     */
    public function testGzipStreamMode(string $mode, bool $readable, bool $writable): void
    {
        $handle = gzopen('php://temp', $mode);
        $stream = new Stream($handle);
        self::assertSame($readable, $stream->isReadable());
        self::assertSame($writable, $stream->isWritable());
        $stream->close();
    }

    /**
     * @return array<array{string,bool,bool}>
     */
    public function gzipStreamModeProvider(): array
    {
        return [
            ['wb6f', false, true],
            ['wb1h', false, true],
            ['rb2', true, false],
        ];
    }

    public function testUnreadableStream(): void
    {
        $dir = File::createTempDir();
        $file = $dir . '/file';
        $handle = File::open($file, 'w');
        $stream = new Stream($handle);
        $stream->write('foo');
        $stream->seek(0);
        $this->expectException(StreamInvalidRequestException::class);
        try {
            $stream->getContents();
        } finally {
            $stream->close();
            File::pruneDir($dir);
            rmdir($dir);
        }
    }

    private function getStream(string $mode = 'r+', ?string $data = 'data', string $filename = 'php://temp'): Stream
    {
        $handle = File::open($filename, $mode);
        if ($data !== null) {
            fwrite($handle, 'data');
        }
        $this->LastHandle = $handle;
        return new Stream($handle);
    }

    private function getForwardOnlyStream(): Stream
    {
        $handle = popen('echo data', 'r');
        $this->LastHandle = $handle;
        return new Stream($handle);
    }
}
