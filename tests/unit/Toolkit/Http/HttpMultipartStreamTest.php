<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Http\Message\StreamPartInterface;
use Salient\Http\Exception\StreamDetachedException;
use Salient\Http\Exception\StreamInvalidRequestException;
use Salient\Http\MultipartStream;
use Salient\Http\Request;
use Salient\Http\Stream;
use Salient\Http\StreamPart;
use Salient\Tests\TestCase;
use Salient\Utility\File;
use Salient\Utility\Str;
use Salient\Utility\Sys;
use InvalidArgumentException;

/**
 * @covers \Salient\Http\MultipartStream
 * @covers \Salient\Http\Request
 * @covers \Salient\Http\AbstractRequest
 * @covers \Salient\Http\AbstractMessage
 * @covers \Salient\Http\HasInnerHeadersTrait
 * @covers \Salient\Http\Headers
 */
final class HttpMultipartStreamTest extends TestCase
{
    private const CONTENTS_PART1 =
        "--boundary\r\n"
        . "Content-Disposition: form-data; name=\"field1\"\r\n"
        . "\r\n"
        . "value1\r\n";

    private const CONTENTS_PART2 =
        "--boundary\r\n"
        . "Content-Disposition: form-data; name=\"field2\"; filename=\"example2.txt\"\r\n"
        . "\r\n"
        . "value2\r\n";

    private const CONTENTS_PART3 =
        "--boundary\r\n"
        . "Content-Disposition: form-data; name=\"field3\"; filename=\"example3.txt\"; filename*=UTF-8''example%203-%C3%A4-%E2%82%AC.txt\r\n"
        . "Content-Type: text/plain\r\n"
        . "\r\n"
        . "value3\r\n";

    private const CONTENTS_PART4 =
        "--boundary\r\n"
        . "Content-Disposition: form-data; name=\"field4\"; filename=\"example4.txt\"\r\n"
        . "\r\n"
        . "value4\r\n";

    private const CONTENTS_FINAL =
        "--boundary--\r\n";

    private const CONTENTS =
        self::CONTENTS_PART1
        . self::CONTENTS_PART2
        . self::CONTENTS_PART3
        . self::CONTENTS_PART4
        . self::CONTENTS_FINAL;

    /** @var resource */
    private $LastHandle;

    public function testStream(): void
    {
        $stream = $this->getStream();
        $this->assertTrue($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertSame('boundary', $stream->getBoundary());
        $this->assertSame($length = strlen(self::CONTENTS), $stream->getSize());
        $this->assertSame([], $stream->getMetadata());
        $this->assertNull($stream->getMetadata('uri'));
        $this->assertSame(0, $stream->tell());
        $this->assertFalse($stream->eof());
        $this->assertSame(self::CONTENTS, (string) $stream);
        $this->assertSame(self::CONTENTS, (string) $stream);
        $this->assertSame('', $stream->getContents());
        $this->assertTrue($stream->eof());
        $stream->seek(0);
        $this->assertFalse($stream->eof());
        $this->assertSame(self::CONTENTS_PART1, $stream->read($length1 = strlen(self::CONTENTS_PART1)));
        $this->assertSame($length1, $stream->tell());
        $stream->seek(0, \SEEK_CUR);
        $this->assertSame($length1, $stream->tell());
        $stream->seek($length2 = strlen(self::CONTENTS_PART2), \SEEK_CUR);
        $this->assertSame(self::CONTENTS_PART3, $stream->read($length3 = strlen(self::CONTENTS_PART3)));
        $this->assertSame($length1 + $length2 + $length3, $stream->tell());
        $stream->seek(-($lengthFinal = strlen(self::CONTENTS_FINAL)), \SEEK_END);
        $this->assertSame($length - $lengthFinal, $stream->tell());
        $this->assertSame(self::CONTENTS_FINAL, $stream->getContents());
        $stream->close();
    }

    public function testWrite(): void
    {
        $stream = $this->getStream();
        $this->expectException(StreamInvalidRequestException::class);
        $this->expectExceptionMessage('Stream is not writable');
        $stream->write('foo');
    }

    public function testDetach(): void
    {
        $stream = $this->getStream();
        $this->assertNull($stream->detach());
        $this->assertIsResource($this->LastHandle, 'Underlying PHP stream should not be closed');
        $this->expectException(StreamDetachedException::class);
        $this->expectExceptionMessage('Stream is closed or detached');
        (string) $stream;
    }

    public function testClose(): void
    {
        $stream = $this->getStream();
        $this->assertIsResource($this->LastHandle);
        $stream->close();
        $this->assertFalse(is_resource($this->LastHandle));
        $this->expectException(StreamDetachedException::class);
        $this->expectExceptionMessage('Stream is closed or detached');
        (string) $stream;
    }

    public function testReadInvalidLength(): void
    {
        $stream = $this->getStream();
        $this->assertSame('', $stream->read(0));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($length) must be greater than or equal to 0');
        $stream->read(-1);
    }

    public function testInvalidSeek(): void
    {
        $stream = $this->getStream();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid offset relative to position 0: -10');
        $stream->seek(-10);
    }

    public function testUnreadableStream(): void
    {
        $dir = File::createTempDir();
        $file = $dir . '/file';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Body not readable: 4');
        try {
            $this->getStream(
                new StreamPart(new Stream(File::open($file, 'w')), 'unreadable'),
            );
        } finally {
            File::pruneDir($dir, true);
        }
    }

    public function testUnseekableStream(): void
    {
        $command = Sys::escapeCommand([...self::PHP_COMMAND, '-r', "echo 'data';"]);
        $stream = $this->getStream(
            new StreamPart(new Stream(File::openPipe($command, 'r')), 'unseekable'),
        );
        $this->assertFalse($stream->isSeekable());
        $this->assertSame($this->getContents(
            "--boundary\r\n"
            . "Content-Disposition: form-data; name=\"unseekable\"\r\n"
            . "\r\n"
            . "data\r\n"
        ), (string) $stream);
        $this->expectException(StreamInvalidRequestException::class);
        $this->expectExceptionMessage('Stream is not seekable');
        $stream->seek(0);
    }

    public function testWithRequest(): void
    {
        $stream = $this->getStream();
        $request = new Request('POST', 'https://example.com', $stream);
        $this->assertSame(($headers = "POST / HTTP/1.1\r\n"
                . "Content-Type: multipart/form-data; boundary=boundary\r\n"
                . "Host: example.com\r\n\r\n")
            . self::CONTENTS, (string) $request);
        $this->assertSame($headers, (string) $request->withBody(null));
    }

    private function getStream(StreamPartInterface ...$streams): MultipartStream
    {
        $handle = Str::toStream('value1');
        $this->LastHandle = $handle;
        return new MultipartStream([
            new StreamPart($handle, 'field1'),
            new StreamPart('value2', 'field2', 'example2.txt'),
            new StreamPart('value3', 'field3', 'example 3-ä-€.txt', 'text/plain', 'example3.txt'),
            new StreamPart('value4', 'field4', null, null, 'example4.txt'),
            ...$streams,
        ], 'boundary');
    }

    private function getContents(string ...$streamContents): string
    {
        return self::CONTENTS_PART1
            . self::CONTENTS_PART2
            . self::CONTENTS_PART3
            . self::CONTENTS_PART4
            . implode('', $streamContents)
            . self::CONTENTS_FINAL;
    }
}
