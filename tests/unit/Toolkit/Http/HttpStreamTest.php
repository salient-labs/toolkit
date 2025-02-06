<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Contract\Http\FormDataFlag;
use Salient\Core\Date\DateFormatter;
use Salient\Http\Exception\StreamDetachedException;
use Salient\Http\Exception\StreamEncapsulationException;
use Salient\Http\Exception\StreamInvalidRequestException;
use Salient\Http\HttpMultipartStreamPart;
use Salient\Http\HttpStream;
use Salient\Tests\TestCase;
use Salient\Utility\File;
use Salient\Utility\Format;
use Salient\Utility\Sys;
use DateTimeImmutable;
use InvalidArgumentException;
use stdClass;
use Throwable;

/**
 * Some tests are derived from similar guzzlehttp/psr7 tests
 *
 * @covers \Salient\Http\HttpStream
 */
final class HttpStreamTest extends TestCase
{
    /** @var resource|null */
    private $LastHandle;

    public function testInvalidResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Argument #1 ($stream) must be of type resource (stream), string given'
        );
        // @phpstan-ignore argument.type
        new HttpStream(__METHOD__);
    }

    /**
     * @dataProvider streamProvider
     */
    public function testStream(string $mode): void
    {
        $stream = $this->getStream($mode);
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertTrue($stream->isSeekable());
        $this->assertSame('php://temp', $stream->getMetadata('uri'));
        $this->assertIsArray($stream->getMetadata());
        $this->assertSame(4, $stream->getSize());
        $this->assertFalse($stream->eof());
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

    public function testFromString(): void
    {
        $stream = HttpStream::fromString('foo');
        $this->assertSame(3, $stream->getSize());
        $this->assertSame('foo', (string) $stream);
        $stream->close();
    }

    /**
     * @dataProvider fromDataProvider
     *
     * @param mixed[]|object $data
     * @param int-mask-of<FormDataFlag::*> $flags
     */
    public function testFromData(
        ?string $expected,
        $data,
        int $flags = FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
        ?DateFormatter $dateFormatter = null,
        bool $asJson = false
    ): void {
        if ($expected === null) {
            $this->expectException(StreamEncapsulationException::class);
        }
        $stream = HttpStream::fromData($data, $flags, $dateFormatter, $asJson, 'boundary');
        $this->assertSame($expected, (string) $stream);
        $stream->close();
    }

    /**
     * @return array<array{string|null,mixed[]|object,2?:int-mask-of<FormDataFlag::*>,3?:DateFormatter|null,4?:bool}>
     */
    public static function fromDataProvider(): array
    {
        $date = new DateTimeImmutable('2021-10-02T17:23:14+10:00');
        $data = [
            'user_id' => 7654,
            'fields' => [
                'email' => 'JWilliams432@gmail.com',
                'groups' => ['staff', 'editor'],
                'created' => $date,
            ],
        ];

        $file = self::getFixturesPath(__CLASS__) . '/profile.gif';
        $multipartData = $data;
        $multipartData['fields']['profile_image'] = HttpMultipartStreamPart::fromFile($file);
        $content = File::getContents($file);
        $multipartBody =
            "--boundary\r\n"
            . "Content-Disposition: form-data; name=\"user_id\"\r\n\r\n"
            . "7654\r\n"
            . "--boundary\r\n"
            . "Content-Disposition: form-data; name=\"fields[email]\"\r\n\r\n"
            . "JWilliams432@gmail.com\r\n"
            . "--boundary\r\n"
            . "Content-Disposition: form-data; name=\"fields[groups][]\"\r\n\r\n"
            . "staff\r\n"
            . "--boundary\r\n"
            . "Content-Disposition: form-data; name=\"fields[groups][]\"\r\n\r\n"
            . "editor\r\n"
            . "--boundary\r\n"
            . "Content-Disposition: form-data; name=\"fields[created]\"\r\n\r\n"
            . "2021-10-02T17:23:14+10:00\r\n"
            . "--boundary\r\n"
            . "Content-Disposition: form-data; name=\"fields[profile_image]\"; filename=\"profile.gif\"\r\n"
            . "Content-Type: image/gif\r\n\r\n"
            . $content . "\r\n"
            . "--boundary--\r\n";

        $object = new stdClass();
        $object->BillingId = 123;
        $objectData = $data;
        $objectData['attributes'] = $object;

        return [
            [
                // user_id=7654&fields[email]=JWilliams432@gmail.com&fields[groups][]=staff&fields[groups][]=editor&fields[created]=2021-10-02T17:23:14+10:00
                'user_id=7654&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00',
                $data,
            ],
            [
                $multipartBody,
                $multipartData,
            ],
            [
                // user_id=7654&fields[email]=JWilliams432@gmail.com&fields[groups][]=staff&fields[groups][]=editor&fields[created]=2021-10-02T17:23:14+10:00&attributes[BillingId]=123
                'user_id=7654&fields%5Bemail%5D=JWilliams432%40gmail.com&fields%5Bgroups%5D%5B%5D=staff&fields%5Bgroups%5D%5B%5D=editor&fields%5Bcreated%5D=2021-10-02T17%3A23%3A14%2B10%3A00&attributes%5BBillingId%5D=123',
                $objectData,
            ],
            [
                '{"user_id":7654,"fields":{"email":"JWilliams432@gmail.com","groups":["staff","editor"],"created":"2021-10-02T17:23:14+10:00"}}',
                $data,
                FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
                null,
                true,
            ],
            [
                null,
                $multipartData,
                FormDataFlag::PRESERVE_NUMERIC_KEYS | FormDataFlag::PRESERVE_STRING_KEYS,
                null,
                true,
            ],
        ];
    }

    public function testToString(): void
    {
        $stream = $this->getStream();
        $this->assertSame('data', (string) $stream);
        $this->assertSame('data', (string) $stream);
        $stream->close();
    }

    public function testForwardOnlyStream(): void
    {
        $stream = $this->getForwardOnlyStream();
        $this->assertFalse($stream->isSeekable());
        $this->assertSame('data', (string) $stream);
        $stream->close();

        $stream = $this->getForwardOnlyStream();
        $firstLetter = $stream->read(1);
        $this->assertFalse($stream->isSeekable());
        $this->assertSame('d', $firstLetter);
        $this->assertSame('ata', (string) $stream);
        $stream->close();
    }

    public function testGetContents(): void
    {
        $stream = $this->getStream();
        $this->assertSame('', $stream->getContents());
        $stream->seek(0);
        $this->assertSame('data', $stream->getContents());
        $this->assertSame('', $stream->getContents());
        $stream->close();
    }

    public function testEof(): void
    {
        $stream = $this->getStream();
        $this->assertSame(4, $stream->tell());
        $this->assertFalse($stream->eof());
        $this->assertSame('', $stream->read(1));
        $this->assertTrue($stream->eof());
        $stream->close();
    }

    public function testGetSize(): void
    {
        $size = File::size(__FILE__);
        $handle = File::open(__FILE__, 'r');
        $stream = new HttpStream($handle);
        $this->assertSame($size, $stream->getSize());
        $this->assertSame($size, $stream->getSize());
        $stream->close();

        $stream = $this->getStream();
        $this->assertSame(4, $stream->getSize());
        $this->assertSame(3, $stream->write('123'));
        $this->assertSame(7, $stream->getSize());
        $this->assertSame(7, $stream->getSize());
        $stream->close();
    }

    public function testTell(): void
    {
        $stream = $this->getStream('r+', null);
        $this->assertSame(0, $stream->tell());
        $stream->write('foo');
        $this->assertSame(3, $stream->tell());
        $stream->seek(1);
        $this->assertSame(1, $stream->tell());
        $this->assertNotNull($this->LastHandle);
        $this->assertSame(ftell($this->LastHandle), $stream->tell());
        $stream->close();
    }

    public function testDetach(): void
    {
        $stream = $this->getStream('r', null);
        $this->assertSame($this->LastHandle, $stream->detach());
        $this->assertIsResource($this->LastHandle, 'Underlying PHP stream should not be closed');
        $this->assertNull($stream->detach());
        $this->assertDetached($stream);
        $stream->close();
        $this->assertNotNull($this->LastHandle);
        File::close($this->LastHandle);
    }

    public function testClose(): void
    {
        $stream = $this->getStream('r', null);
        $this->assertIsResource($this->LastHandle);
        $stream->close();
        $this->assertFalse(is_resource($this->LastHandle));
        $this->assertDetached($stream);
    }

    private function assertDetached(HttpStream $stream): void
    {
        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertFalse($stream->isSeekable());

        $throws = function (callable $fn): void {
            try {
                $fn();
            } catch (Throwable $e) {
                $this->assertStringContainsString('Stream is detached', $e->getMessage());

                return;
            }

            $this->fail('Exception should be thrown after the stream is detached.');
        };

        foreach ([
            'getSize' => fn() => $stream->getSize(),
            'getMetadata' => fn() => $stream->getMetadata(),
            '__toString' => fn() => (string) $stream,
            'getContents' => fn() => $stream->getContents(),
            'tell' => fn() => $stream->tell(),
            'eof' => fn() => $stream->eof(),
            'rewind' => fn() => $stream->rewind(),
            'read' => fn() => $stream->read(1),
            'write' => fn() => $stream->write('foo'),
            'seek' => fn() => $stream->seek(0),
        ] as $method => $callback) {
            $this->assertCallbackThrowsException(
                $callback,
                StreamDetachedException::class,
                'Stream is detached',
                sprintf('%s::%s() should throw an exception after stream is detached', HttpStream::class, $method)
            );
        }
    }

    public function testReadInvalidLength(): void
    {
        $stream = $this->getStream('r', null);
        $this->assertSame('', $stream->read(0));
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
            /** @var string */
            $actualMode = $stream->getMetadata('mode');
            $this->assertSame(
                $readable,
                $stream->isReadable(),
                sprintf('isReadable() should be %s for mode %s (actual mode: %s)', Format::bool($readable), $mode, $actualMode)
            );
            $this->assertSame(
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
                File::pruneDir($dir, true);
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
     * @dataProvider gzipStreamModeProvider
     */
    public function testGzipStreamMode(string $mode, bool $readable, bool $writable): void
    {
        $handle = gzopen('php://temp', $mode);
        $this->assertIsResource($handle);
        $stream = new HttpStream($handle);
        $this->assertSame($readable, $stream->isReadable());
        $this->assertSame($writable, $stream->isWritable());
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
        $stream = new HttpStream($handle);
        $stream->write('foo');
        $stream->seek(0);
        $this->expectException(StreamInvalidRequestException::class);
        $this->expectExceptionMessage('Stream is not open for reading');
        try {
            $stream->getContents();
        } finally {
            $stream->close();
            File::pruneDir($dir, true);
        }
    }

    public function testUnwritableStream(): void
    {
        $dir = File::createTempDir();
        $file = $dir . '/file';
        touch($file);
        $handle = File::open($file, 'r');
        $stream = new HttpStream($handle);
        $this->expectException(StreamInvalidRequestException::class);
        $this->expectExceptionMessage('Stream is not open for writing');
        try {
            $stream->write('foo');
        } finally {
            $stream->close();
            File::pruneDir($dir, true);
        }
    }

    public function testUnseekableStream(): void
    {
        $stream = $this->getForwardOnlyStream();
        $this->expectException(StreamInvalidRequestException::class);
        $this->expectExceptionMessage('Stream is not seekable');
        try {
            $stream->seek(0);
        } finally {
            $stream->getContents();
            $stream->close();
        }
    }

    /**
     * @dataProvider copyProvider
     */
    public function testCopy(string $data): void
    {
        $from = $this->getStream('r+', $data);
        $from->rewind();
        $to = $this->getStream('r+', null);
        try {
            HttpStream::copyToStream($from, $to);
            $this->assertSame($data, (string) $to);
        } finally {
            $from->close();
            $to->close();
        }
    }

    /**
     * @return array<array{string}>
     */
    public static function copyProvider(): array
    {
        return [
            [''],
            ['data'],
            // 32 KiB
            [str_repeat('0123456789abcdef', 2048)],
        ];
    }

    private function getStream(string $mode = 'r+', ?string $data = 'data', string $filename = 'php://temp'): HttpStream
    {
        $handle = File::open($filename, $mode);
        if ($data !== null) {
            File::write($handle, $data);
        }
        $this->LastHandle = $handle;
        return new HttpStream($handle);
    }

    private function getForwardOnlyStream(): HttpStream
    {
        $command = Sys::escapeCommand([...self::PHP_COMMAND, '-r', "echo 'data';"]);
        $handle = File::openPipe($command, 'r');
        $this->LastHandle = $handle;
        return new HttpStream($handle);
    }
}
