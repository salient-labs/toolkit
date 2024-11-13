<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Http\HttpMultipartStreamPart;
use Salient\Http\HttpStream;
use Salient\Tests\TestCase;
use Salient\Utility\File;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;

/**
 * @covers \Salient\Http\HttpMultipartStreamPart
 */
class HttpMultipartStreamPartTest extends TestCase
{
    public function testConstructor(): void
    {
        $content = 'Hello, world!';

        $p = new HttpMultipartStreamPart($content, 'file');
        $this->assertEquals('file', $p->getName());
        $this->assertNull($p->getFilename());
        $this->assertNull($p->getFallbackFilename());
        $this->assertNull($p->getMediaType());
        $this->assertEquals($content, (string) $p->getContent());

        $p = new HttpMultipartStreamPart(Str::toStream($content), 'file', 'file.txt', 'text/plain');
        $this->assertEquals('file.txt', $p->getFilename());
        $this->assertEquals('file.txt', $p->getFallbackFilename());
        $this->assertEquals('text/plain', $p->getMediaType());
        $this->assertEquals($content, (string) $p->getContent());

        $p = new HttpMultipartStreamPart(HttpStream::fromString($content), 'file', '');
        $this->assertNull($p->getFilename());
        $this->assertNull($p->getFallbackFilename());
        $this->assertEquals($content, (string) $p->getContent());

        $p = new HttpMultipartStreamPart(null, 'file', '%.txt', null, '');
        $this->assertEquals('%.txt', $p->getFilename());
        $this->assertEquals('%.txt', $p->getFallbackFilename());
        $this->assertEquals('', (string) $p->getContent());
    }

    public function testWithName(): void
    {
        $p = new HttpMultipartStreamPart('');
        $this->assertSame('file', $p->withName('file')->getName());
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Name is not set');
        $p->getName();
    }

    public function testFromFile(): void
    {
        $p = HttpMultipartStreamPart::fromFile(__FILE__, 'upload');
        $this->assertSame('upload', $p->getName());
        $this->assertSame($basename = basename(__FILE__), $p->getFilename());
        $this->assertSame($basename, $p->getFallbackFilename());
        $this->assertSame('text/x-php', $p->getMediaType());
        $this->assertSame(File::getContents(__FILE__), (string) $p->getContent());

        $p = HttpMultipartStreamPart::fromFile(__FILE__, 'upload', 'source.php', 'application/x-httpd-php', 'fallback.php');
        $this->assertSame('source.php', $p->getFilename());
        $this->assertSame('fallback.php', $p->getFallbackFilename());
        $this->assertSame('application/x-httpd-php', $p->getMediaType());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found: ');
        HttpMultipartStreamPart::fromFile(__DIR__ . '/does_not_exist');
    }

    public function testInvalidContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($content) must be of type StreamInterface|resource|string|null, int given');
        // @phpstan-ignore argument.type
        new HttpMultipartStreamPart(123, 'file');
    }

    /**
     * @dataProvider invalidFilenameProvider
     */
    public function testInvalidFilename(string $filename): void
    {
        $p = new HttpMultipartStreamPart(null, 'file', $filename);
        $this->assertSame($filename, $p->getFilename());
        $this->assertNull($p->getFallbackFilename());

        $p = new HttpMultipartStreamPart(null, 'file', $filename, null, 'file.txt');
        $this->assertSame($filename, $p->getFilename());
        $this->assertSame('file.txt', $p->getFallbackFilename());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid fallback filename: ');
        new HttpMultipartStreamPart(null, 'file', 'file.txt', null, $filename);
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
