<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;
use Salient\Http\Stream;
use Salient\Http\StreamPart;
use Salient\Tests\TestCase;
use Salient\Utility\File;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;

/**
 * @covers \Salient\Http\StreamPart
 */
class HttpMultipartStreamPartTest extends TestCase
{
    public function testConstructor(): void
    {
        $content = 'Hello, world!';

        $p = new StreamPart($content, 'file');
        $this->assertEquals('file', $p->getName());
        $this->assertNull($p->getFilename());
        $this->assertNull($p->getAsciiFilename());
        $this->assertNull($p->getMediaType());
        $this->assertEquals($content, (string) $p->getBody());

        $p = new StreamPart(Str::toStream($content), 'file', 'file.txt', 'text/plain');
        $this->assertEquals('file.txt', $p->getFilename());
        $this->assertEquals('file.txt', $p->getAsciiFilename());
        $this->assertEquals('text/plain', $p->getMediaType());
        $this->assertEquals($content, (string) $p->getBody());

        $p = new StreamPart(Stream::fromString($content), 'file', '');
        $this->assertNull($p->getFilename());
        $this->assertNull($p->getAsciiFilename());
        $this->assertEquals($content, (string) $p->getBody());

        $p = new StreamPart(null, 'file', '%.txt', null, '');
        $this->assertEquals('%.txt', $p->getFilename());
        $this->assertEquals('%.txt', $p->getAsciiFilename());
        $this->assertEquals('', (string) $p->getBody());
    }

    public function testWithName(): void
    {
        $p = new StreamPart('');
        $this->assertSame('file', $p->withName('file')->getName());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Name is not set');
        $p->getName();
    }

    public function testFromFile(): void
    {
        $p = StreamPart::fromFile(__FILE__, 'upload');
        $this->assertSame('upload', $p->getName());
        $this->assertSame($basename = basename(__FILE__), $p->getFilename());
        $this->assertSame($basename, $p->getAsciiFilename());
        $this->assertSame('text/x-php', $p->getMediaType());
        $this->assertSame(File::getContents(__FILE__), (string) $p->getBody());

        $p = StreamPart::fromFile(__FILE__, 'upload', 'source.php', 'application/x-httpd-php', 'fallback.php');
        $this->assertSame('source.php', $p->getFilename());
        $this->assertSame('fallback.php', $p->getAsciiFilename());
        $this->assertSame('application/x-httpd-php', $p->getMediaType());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found: ');
        StreamPart::fromFile(__DIR__ . '/does_not_exist');
    }

    public function testInvalidContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($content) must be of type ' . PsrStreamInterface::class . '|resource|string|null, int given');
        // @phpstan-ignore argument.type
        new StreamPart(123, 'file');
    }

    /**
     * @dataProvider invalidFilenameProvider
     */
    public function testInvalidFilename(string $filename): void
    {
        $p = new StreamPart(null, 'file', $filename);
        $this->assertSame($filename, $p->getFilename());
        $this->assertNull($p->getAsciiFilename());

        $p = new StreamPart(null, 'file', $filename, null, 'file.txt');
        $this->assertSame($filename, $p->getFilename());
        $this->assertSame('file.txt', $p->getAsciiFilename());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid fallback filename: ');
        new StreamPart(null, 'file', 'file.txt', null, $filename);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function invalidFilenameProvider(): array
    {
        return [
            'not ASCII' => ['äëïöüÿ.txt'],
            'percent-encoded' => ['file%2b1.txt'],
            'has backslash' => ['dir\file.txt'],
            'has quote' => ['"file".txt'],
        ];
    }
}
