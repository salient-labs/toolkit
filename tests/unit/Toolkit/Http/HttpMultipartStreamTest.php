<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Http\HttpMultipartStreamPartInterface;
use Salient\Core\Utility\File;
use Salient\Core\Utility\Str;
use Salient\Core\Utility\Sys;
use Salient\Http\Exception\StreamInvalidRequestException;
use Salient\Http\HttpMultipartStream;
use Salient\Http\HttpMultipartStreamPart;
use Salient\Http\HttpRequest;
use Salient\Http\HttpStream;
use Salient\Tests\TestCase;
use InvalidArgumentException;

/**
 * @covers \Salient\Http\HttpMultipartStream
 * @covers \Salient\Http\HttpRequest
 * @covers \Salient\Http\AbstractHttpMessage
 * @covers \Salient\Http\HasHttpHeaders
 * @covers \Salient\Http\HttpHeaders
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

    /**
     * @var resource
     */
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
        $this->assertSame(self::CONTENTS_FINAL, (string) $stream);
    }

    public function testClose(): void
    {
        $stream = $this->getStream();
        $this->assertIsResource($this->LastHandle);
        $stream->close();
        $this->assertFalse(is_resource($this->LastHandle));
        $this->assertSame(self::CONTENTS_FINAL, (string) $stream);
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
        $this->expectExceptionMessage('Stream must be readable');
        try {
            $this->getStream(
                new HttpMultipartStreamPart(new HttpStream(File::open($file, 'w')), 'unreadable'),
            );
        } finally {
            File::pruneDir($dir, true);
        }
    }

    public function testUnseekableStream(): void
    {
        $command = Sys::escapeCommand([...self::PHP_COMMAND, '-r', "echo 'data';"]);
        $stream = $this->getStream(
            new HttpMultipartStreamPart(new HttpStream(File::openPipe($command, 'r')), 'unseekable'),
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
        $request = new HttpRequest('POST', 'https://example.com', $stream);
        $this->assertSame(($headers = "POST / HTTP/1.1\r\n"
                . "Content-Type: multipart/form-data; boundary=boundary\r\n"
                . "Host: example.com\r\n\r\n")
            . self::CONTENTS, (string) $request);
        $this->assertSame($headers, $request->getHttpPayload(true));
    }

    private function getStream(HttpMultipartStreamPartInterface ...$streams): HttpMultipartStream
    {
        $handle = Str::toStream('value1');
        $this->LastHandle = $handle;
        return new HttpMultipartStream([
            new HttpMultipartStreamPart($handle, 'field1'),
            new HttpMultipartStreamPart('value2', 'field2', 'example2.txt'),
            new HttpMultipartStreamPart('value3', 'field3', 'example 3-ä-€.txt', 'text/plain', 'example3.txt'),
            new HttpMultipartStreamPart('value4', 'field4', null, null, 'example4.txt'),
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
