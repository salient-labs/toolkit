<?php declare(strict_types=1);

namespace Salient\Tests\Http;

use Salient\Core\Utility\Str;
use Salient\Http\HttpMultipartStreamPart;
use Salient\Http\HttpStream;
use Salient\Tests\TestCase;
use InvalidArgumentException;

/**
 * @covers \Salient\Http\HttpMultipartStreamPart
 */
class HttpMultipartStreamPartTest extends TestCase
{
    public function testConstructor(): void
    {
        $content = 'Hello, world!';

        $p = new HttpMultipartStreamPart('file', $content);
        $this->assertEquals('file', $p->getName());
        $this->assertNull($p->getFilename());
        $this->assertNull($p->getFallbackFilename());
        $this->assertNull($p->getMediaType());
        $this->assertEquals($content, (string) $p->getContent());

        $p = new HttpMultipartStreamPart('file', Str::toStream($content), 'file.txt', 'text/plain');
        $this->assertEquals('file.txt', $p->getFilename());
        $this->assertEquals('file.txt', $p->getFallbackFilename());
        $this->assertEquals('text/plain', $p->getMediaType());
        $this->assertEquals($content, (string) $p->getContent());

        $p = new HttpMultipartStreamPart('file', HttpStream::fromString($content), '');
        $this->assertNull($p->getFilename());
        $this->assertNull($p->getFallbackFilename());
        $this->assertEquals($content, (string) $p->getContent());

        $p = new HttpMultipartStreamPart('file', null, '%.txt', null, '');
        $this->assertEquals('%.txt', $p->getFilename());
        $this->assertEquals('%.txt', $p->getFallbackFilename());
        $this->assertEquals('', (string) $p->getContent());
    }

    public function testInvalidContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument #1 ($content) must be of type StreamInterface|resource|string|null, int given');
        // @phpstan-ignore-next-line
        new HttpMultipartStreamPart('file', 123);
    }

    /**
     * @dataProvider invalidFilenameProvider
     */
    public function testInvalidFilename(string $filename): void
    {
        $p = new HttpMultipartStreamPart('file', null, $filename);
        $this->assertSame($filename, $p->getFilename());
        $this->assertNull($p->getFallbackFilename());

        $p = new HttpMultipartStreamPart('file', null, $filename, null, 'file.txt');
        $this->assertSame($filename, $p->getFilename());
        $this->assertSame('file.txt', $p->getFallbackFilename());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid fallback filename: ');
        new HttpMultipartStreamPart('file', null, 'file.txt', null, $filename);
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
